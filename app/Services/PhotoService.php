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

            $bytes = $file->get();
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
}
