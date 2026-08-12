<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Exam;
use App\Models\Permission;
use App\Models\ScholarInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function scholarUser(): User
    {
        $user = User::factory()->create(['username' => 'scholar-clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'scholars.php',
            'can_access' => true,
        ]);

        $this->logInAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->id,
            'program' => 'CEDSSG',
            'school' => 'ILOCOS SUR POLYTECHNIC STATE COLLEGE-MAIN',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => 'COLLEGE OF INFORMATION TECHNOLOGY',
            'course' => 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY',
            'year_level' => '1ST YEAR',
            'is_regular' => 1,
            'year_start' => '2025',
            'year_end' => '2026',
            'landbank_no' => '1234567890',
        ], $overrides);
    }

    public function test_scholar_can_be_created()
    {
        $this->scholarUser();
        $client = Client::factory()->create();

        $response = $this->post(route('scholars.store'), $this->payload($client));

        $response->assertRedirect(route('scholars.index'));
        $this->assertDatabaseHas('tbl_scholar_info', [
            'client_id' => $client->id,
            'program' => 'CEDSSG',
            'full_name' => '',
            'school' => 'ILOCOS SUR POLYTECHNIC STATE COLLEGE-MAIN',
            'is_regular' => 1,
            'year_started' => '2025 - 2026',
        ]);
        $this->assertDatabaseCount('tbl_update_logs', 0);
    }

    public function test_scholar_defaults_is_regular_to_zero_when_field_absent()
    {
        $this->scholarUser();
        $client = Client::factory()->create();

        $this->post(route('scholars.store'), $this->payload($client, ['is_regular' => null]));

        $this->assertDatabaseHas('tbl_scholar_info', [
            'client_id' => $client->id,
            'is_regular' => 0,
        ]);
    }

    public function test_scholar_year_started_allows_one_sided_year()
    {
        $this->scholarUser();
        $client = Client::factory()->create();

        $this->post(route('scholars.store'), $this->payload($client, ['year_end' => '']));

        $this->assertDatabaseHas('tbl_scholar_info', [
            'client_id' => $client->id,
            'year_started' => '2025',
        ]);
    }

    public function test_scholar_year_started_is_empty_string_when_both_sides_empty()
    {
        $this->scholarUser();
        $client = Client::factory()->create();

        $this->post(route('scholars.store'), $this->payload($client, ['year_start' => '', 'year_end' => '']));

        $this->assertDatabaseHas('tbl_scholar_info', [
            'client_id' => $client->id,
            'year_started' => '',
        ]);
    }

    public function test_scholar_accepts_empty_optional_fields()
    {
        $this->scholarUser();
        $client = Client::factory()->create();

        $this->post(route('scholars.store'), $this->payload($client, [
            'school' => '',
            'school_type' => '',
            'campus' => '',
            'college_department' => '',
            'course' => '',
            'year_level' => '',
            'landbank_no' => '',
        ]));

        $this->assertDatabaseHas('tbl_scholar_info', [
            'client_id' => $client->id,
            'school' => '',
            'school_type' => '',
            'campus' => '',
            'college_department' => '',
            'course' => '',
            'year_level' => '',
            'landbank_no' => '',
        ]);
    }

    public function test_scholar_update_upserts_latest_row_and_keeps_full_name()
    {
        $this->scholarUser();
        $client = Client::factory()->create();
        $scholar = ScholarInfo::create([
            'client_id' => $client->id,
            'full_name' => 'JUAN, DELA CRUZ',
            'program' => 'CEDSSG',
            'school' => 'OLD SCHOOL',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => 'ENGINEERING',
            'course' => 'BSCE',
            'year_level' => '1ST YEAR',
            'is_regular' => 1,
            'year_started' => '2024 - 2025',
            'landbank_no' => '111',
        ]);

        $response = $this->put(route('scholars.update', $scholar->id), $this->payload($client, [
            'program' => 'CEDSSG',
            'school' => 'NEW SCHOOL',
            'year_level' => '2ND YEAR',
            'is_regular' => 0,
            'year_start' => '2026',
            'year_end' => '2027',
        ]));

        $response->assertRedirect(route('scholars.index'));
        $this->assertDatabaseHas('tbl_scholar_info', [
            'id' => $scholar->id,
            'school' => 'NEW SCHOOL',
            'year_level' => '2ND YEAR',
            'is_regular' => 0,
            'year_started' => '2026 - 2027',
            'full_name' => 'JUAN, DELA CRUZ',
        ]);
        $this->assertDatabaseCount('tbl_update_logs', 0);
    }

    public function test_scholar_data_feed_joins_exam_and_orders_by_client_id()
    {
        $this->scholarUser();

        $clientOne = Client::factory()->create(['lastname' => 'DE LOS SANTOS', 'firstname' => 'ANA']);
        $clientTwo = Client::factory()->create(['lastname' => 'CRUZ', 'firstname' => 'JUAN']);

        ScholarInfo::create([
            'client_id' => $clientOne->id,
            'full_name' => 'DE LOS SANTOS, ANA',
            'program' => 'CEDSSG',
            'school' => 'ISPSC-MAIN',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => '',
            'course' => '',
            'year_level' => '1ST YEAR',
            'is_regular' => 1,
            'year_started' => '2025 - 2026',
            'landbank_no' => '',
        ]);

        ScholarInfo::create([
            'client_id' => $clientTwo->id,
            'full_name' => 'CRUZ, JUAN',
            'program' => 'CEAP',
            'school' => 'ISPSC-MAIN',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => '',
            'course' => '',
            'year_level' => '2ND YEAR',
            'is_regular' => 1,
            'year_started' => '2025 - 2026',
            'landbank_no' => '',
        ]);

        Exam::create([
            'exam_no' => 'EX-001',
            'fullname' => 'cruz, juan',
            'barangay' => 'BARANGAY I',
            'town' => 'VIGAN',
            'email_address' => 'juan@example.com',
            'contact' => '0917',
            'school' => 'ISPSC-MAIN',
            'course' => 'BSIT',
            'year' => 2026,
            'scholarship' => 'CEAP',
            'exam_date' => '2026-01-01',
            'exam_time' => '08:00',
            'score' => '90',
        ]);

        $response = $this->post(route('scholars.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => ''],
        ]);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame(2, $payload['recordsTotal']);
        $this->assertSame(2, $payload['recordsFiltered']);
        $this->assertCount(2, $payload['data']);
        $this->assertSame($clientOne->id, $payload['data'][0]['client_id']);

        $byName = collect($payload['data'])->keyBy('full_name');
        $this->assertSame('BARANGAY I', $byName['CRUZ, JUAN']['barangay']);
        $this->assertSame('VIGAN', $byName['CRUZ, JUAN']['town']);
        $this->assertNull($byName['DE LOS SANTOS, ANA']['barangay']);
    }

    public function test_scholar_data_feed_applies_search_and_reports_filtered_total()
    {
        $this->scholarUser();

        $clientOne = Client::factory()->create(['lastname' => 'CRUZ', 'firstname' => 'JUAN']);
        $clientTwo = Client::factory()->create(['lastname' => 'REYES', 'firstname' => 'MARIA']);

        ScholarInfo::create([
            'client_id' => $clientOne->id,
            'full_name' => 'CRUZ, JUAN',
            'program' => 'CEDSSG',
            'school' => 'ISPSC-MAIN',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => '',
            'course' => '',
            'year_level' => '1ST YEAR',
            'is_regular' => 1,
            'year_started' => '2025 - 2026',
            'landbank_no' => '',
        ]);

        ScholarInfo::create([
            'client_id' => $clientTwo->id,
            'full_name' => 'REYES, MARIA',
            'program' => 'CEAP',
            'school' => 'ISPSC-MAIN',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => '',
            'course' => '',
            'year_level' => '2ND YEAR',
            'is_regular' => 1,
            'year_started' => '2025 - 2026',
            'landbank_no' => '',
        ]);

        $response = $this->post(route('scholars.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => 'CRUZ'],
        ]);

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame(1, $payload['recordsTotal']);
        $this->assertSame(1, $payload['recordsFiltered']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('CRUZ, JUAN', $payload['data'][0]['full_name']);
    }

    public function test_scholar_client_id_can_be_relinked()
    {
        $this->scholarUser();
        $clientOne = Client::factory()->create(['lastname' => 'CRUZ', 'firstname' => 'JUAN']);
        $clientTwo = Client::factory()->create(['lastname' => 'REYES', 'firstname' => 'MARIA']);

        $scholar = ScholarInfo::create([
            'client_id' => $clientOne->id,
            'full_name' => 'CRUZ, JUAN',
            'program' => 'CEDSSG',
            'school' => 'ISPSC-MAIN',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => '',
            'course' => '',
            'year_level' => '1ST YEAR',
            'is_regular' => 1,
            'year_started' => '2025 - 2026',
            'landbank_no' => '',
        ]);

        $response = $this->post(route('scholars.update-client-id'), [
            'id' => $scholar->id,
            'client_id' => $clientTwo->id,
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'success']);
        $this->assertDatabaseHas('tbl_scholar_info', [
            'id' => $scholar->id,
            'client_id' => $clientTwo->id,
        ]);
    }

    public function test_scholar_relink_rejects_missing_input_with_http_400()
    {
        $this->scholarUser();
        $client = Client::factory()->create();

        $scholar = ScholarInfo::create([
            'client_id' => $client->id,
            'full_name' => 'CRUZ, JUAN',
            'program' => 'CEDSSG',
            'school' => 'ISPSC-MAIN',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => '',
            'course' => '',
            'year_level' => '1ST YEAR',
            'is_regular' => 1,
            'year_started' => '2025 - 2026',
            'landbank_no' => '',
        ]);

        $response = $this->post(route('scholars.update-client-id'), ['id' => $scholar->id]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('tbl_scholar_info', [
            'id' => $scholar->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_scholar_relink_rejects_nonexistent_client_with_http_400()
    {
        $this->scholarUser();
        $client = Client::factory()->create();

        $scholar = ScholarInfo::create([
            'client_id' => $client->id,
            'full_name' => 'CRUZ, JUAN',
            'program' => 'CEDSSG',
            'school' => 'ISPSC-MAIN',
            'school_type' => 'PUBLIC',
            'campus' => 'MAIN',
            'college_department' => '',
            'course' => '',
            'year_level' => '1ST YEAR',
            'is_regular' => 1,
            'year_started' => '2025 - 2026',
            'landbank_no' => '',
        ]);

        $response = $this->post(route('scholars.update-client-id'), [
            'id' => $scholar->id,
            'client_id' => 999999,
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('tbl_scholar_info', [
            'id' => $scholar->id,
            'client_id' => $client->id,
        ]);
    }
}
