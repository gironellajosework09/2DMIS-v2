<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\ClientPhoto;
use App\Models\Municipality;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $lastname, string $mobile): Client
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN']);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'BARANGAY I',
        ]);

        return Client::query()->create([
            'lastname' => $lastname,
            'firstname' => 'JUAN',
            'middlename' => 'R',
            'city_municipality' => $municipality->id,
            'barangay' => $barangay->id,
            'birthdate' => '1998-01-15',
            'age' => 28,
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'category' => 'YOUTH (18-29)',
            'aff_org' => '',
            'mobile_no' => $mobile,
            'full_name' => "$lastname, JUAN R",
            'match_name' => $lastname.'JUANR',
        ]);
    }

    private function transaction(int $clientId, string $program): Transaction
    {
        return Transaction::query()->create([
            'client_id' => $clientId,
            'program' => $program,
            'patient_name' => 'SELF',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'status' => 'PENDING PAYOUT',
        ]);
    }

    public function test_student_search_lists_only_scholar_clients(): void
    {
        $scholar = $this->client('DELA CRUZ', '09170000001');
        $other = $this->client('SANTOS', '09170000002');

        $this->transaction($scholar->id, 'CEAP');
        $this->transaction($other->id, 'AICS');

        $this->get(route('student.update-photo').'?search=Dela')
            ->assertOk()
            ->assertSee('DELA CRUZ, JUAN R')
            ->assertDontSee('SANTOS, JUAN R');
    }

    public function test_student_search_shows_no_record_found(): void
    {
        $this->get(route('student.update-photo').'?search=zzz')
            ->assertOk()
            ->assertSee('No record found.');
    }

    public function test_student_verify_grants_photo_upload_on_match(): void
    {
        $client = $this->client('DELA CRUZ', '09170000001');

        $this->get(route('student.verify', $client))->assertOk();

        $this->post(route('student.verify.post', $client), [
            'birthdate' => '1998-01-15',
            'mobile' => '09170000001',
        ])->assertRedirect(route('student.photo-upload'));

        $this->assertSame($client->id, session('verified_student'));
    }

    public function test_student_verify_fails_on_mismatch(): void
    {
        $client = $this->client('DELA CRUZ', '09170000001');

        $this->post(route('student.verify.post', $client), [
            'birthdate' => '1998-01-15',
            'mobile' => '09170000002',
        ])->assertSessionHasErrors('verification');

        $this->assertNull(session('verified_student'));
    }

    public function test_student_photo_upload_requires_verification(): void
    {
        $this->get(route('student.photo-upload'))
            ->assertRedirect(route('student.update-photo'));
    }

    public function test_student_can_save_camera_photo_and_session_is_cleared(): void
    {
        Storage::fake('public');
        $client = $this->client('DELA CRUZ', '09170000001');

        $this->withSession(['verified_student' => $client->id])
            ->post(route('student.photo-upload.store'), [
                'camera_image' => 'data:image/jpeg;base64,'.base64_encode("\xFF\xD8\xFF\xE0".random_bytes(16)),
            ])->assertRedirect(route('student.photo-upload'))
            ->assertSessionHas('success');

        $photo = ClientPhoto::query()->firstOrFail();
        $this->assertSame($client->id, $photo->client_id);
        $this->assertSame('CAMERA', $photo->captured_from);

        $this->assertNull(session('verified_student'));
    }

    public function test_student_photo_upload_page_shows_current_photo(): void
    {
        $client = $this->client('DELA CRUZ', '09170000001');

        $this->withSession(['verified_student' => $client->id])
            ->get(route('student.photo-upload'))
            ->assertOk()
            ->assertSee('Take Photo');
    }
}
