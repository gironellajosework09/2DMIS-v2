<?php

namespace App\Services;

use App\Models\ClientPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Port of v1 save_client_photo.php (UPLOAD + CAMERA sources) and the camera
 * save path of v1 student_photo_upload.php. Stores the file on the public
 * disk under uploads/client_photos/ and persists only the filename in
 * tbl_client_photos.photo_path — the same storage contract as v1.
 */
class PhotoService
{
    public const UPLOAD_DIR = 'uploads/client_photos';

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    private const JPEG_MAGIC = "\xFF\xD8\xFF";

    /**
     * Longest-side cap for uploaded photos. Photos wider or taller than this
     * are downscaled (keeping aspect ratio) during storage so avatar files
     * stay small and fast to serve — the v1 upload path had no resizing.
     */
    private const MAX_DIMENSION = 1600;

    /**
     * @param  string|null  $cameraImage  base64 data-URL (camera capture)
     */
    public function store(int $clientId, ?UploadedFile $file, ?string $cameraImage = null): ClientPhoto
    {
        $bytes = null;
        $extension = 'jpg';
        $source = 'UPLOAD';

        if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                throw new InvalidArgumentException('Only JPG, PNG, or GIF images are allowed.');
            }

            $bytes = $this->optimizeImage($file->get(), $extension);
        } elseif (is_string($cameraImage) && $cameraImage !== '') {
            $source = 'CAMERA';
            $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $cameraImage) ?? '';
            $base64 = str_replace(' ', '+', $base64);
            $bytes = base64_decode($base64, true);

            if ($bytes === false || ! str_starts_with($bytes, self::JPEG_MAGIC)) {
                throw new InvalidArgumentException('Invalid camera image.');
            }

            $extension = 'jpg';
        } else {
            throw new InvalidArgumentException('No image provided.');
        }

        $filename = uniqid('', true).'.'.$extension;

        Storage::disk('public')->put(self::UPLOAD_DIR.'/'.$filename, $bytes);

        return ClientPhoto::create([
            'client_id' => $clientId,
            'photo_path' => $filename,
            'captured_from' => $source,
        ]);
    }

    /**
     * Resize-and-re-encode an uploaded image with GD, keeping the original
     * format. Returns the original bytes if GD cannot decode them (so a valid
     * upload is never rejected here — GD is only a storage-time optimization).
     */
    private function optimizeImage(string $bytes, string $extension): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $bytes;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return $bytes;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) > self::MAX_DIMENSION) {
            $scale = self::MAX_DIMENSION / max($width, $height);
            $newW = (int) round($width * $scale);
            $newH = (int) round($height * $scale);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        try {
            switch ($extension) {
                case 'png':
                    $ok = imagepng($image, null, 6);
                    break;
                case 'gif':
                    $ok = imagegif($image);
                    break;
                default:
                    $ok = imagejpeg($image, null, 85);
                    break;
            }
            $optimized = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            imagedestroy($image);

            return $bytes;
        }

        imagedestroy($image);

        if ($ok === false || $optimized === false || $optimized === '') {
            return $bytes;
        }

        return $optimized;
    }
}
