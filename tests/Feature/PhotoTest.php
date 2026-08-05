<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\ClientPhoto;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\User;
use App\Services\PhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create(['username' => 'clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'clients.php',
            'can_access' => true,
        ]);

        return $user;
    }

    private function client(): Client
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN']);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'BARANGAY I',
        ]);

        return Client::query()->create([
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'middlename' => 'S',
            'city_municipality' => $municipality->id,
            'barangay' => $barangay->id,
            'birthdate' => '1990-05-15',
            'age' => 36,
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'category' => 'ADULT (30-59)',
            'aff_org' => '',
            'full_name' => 'DELA CRUZ, JUAN S',
            'match_name' => 'DELACRUZJUANS',
        ]);
    }

    public function test_photo_upload_from_file_stores_photo(): void
    {
        Storage::fake('public');
        $client = $this->client();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.photo.store'), [
            'client_id' => $client->id,
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertRedirect(route('clients.show', $client));

        $photo = ClientPhoto::query()->firstOrFail();
        $this->assertSame($client->id, $photo->client_id);
        $this->assertSame('UPLOAD', $photo->captured_from);
        $this->assertNotEmpty($photo->photo_path);
        Storage::disk('public')->assertExists(PhotoService::UPLOAD_DIR.'/'.$photo->photo_path);
    }

    public function test_photo_upload_from_camera_image(): void
    {
        Storage::fake('public');
        $client = $this->client();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.photo.store'), [
            'client_id' => $client->id,
            'camera_image' => 'data:image/jpeg;base64,'.base64_encode("\xFF\xD8\xFF\xE0".random_bytes(16)),
        ])->assertRedirect(route('clients.show', $client));

        $photo = ClientPhoto::query()->firstOrFail();
        $this->assertSame($client->id, $photo->client_id);
        $this->assertSame('CAMERA', $photo->captured_from);
        Storage::disk('public')->assertExists(PhotoService::UPLOAD_DIR.'/'.$photo->photo_path);
    }

    public function test_photo_upload_rejects_invalid_camera_image(): void
    {
        Storage::fake('public');
        $client = $this->client();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.photo.store'), [
            'client_id' => $client->id,
            'camera_image' => 'data:image/jpeg;base64,not-a-real-image',
        ])->assertSessionHasErrors('photo');

        $this->assertSame(0, ClientPhoto::query()->count());
    }

    public function test_photo_upload_requires_an_image(): void
    {
        Storage::fake('public');
        $client = $this->client();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.photo.store'), [
            'client_id' => $client->id,
        ])->assertSessionHasErrors('photo');

        $this->assertSame(0, ClientPhoto::query()->count());
    }
}
