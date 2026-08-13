<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\ScholarInfo;
use App\Models\Transaction;
use App\Models\UpdateLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GranteeUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function pageUser(string $page): User
    {
        $user = User::factory()->create(['username' => 'logsviewer'.mt_rand(100000, 999999)]);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => $page,
            'can_access' => true,
        ]);

        return $user;
    }

    private function client(array $overrides = []): Client
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN', 'code' => 'VIG']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'BARANGAY I']);

        return Client::query()->create(array_merge([
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'middlename' => 'R',
            'city_municipality' => $municipality->id,
            'barangay' => $barangay->id,
            'birthdate' => '1990-05-15',
            'age' => 36,
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'category' => 'ADULT (30-59)',
            'aff_org' => '',
            'mobile_no' => '09171234567',
            'full_name' => 'DELA CRUZ, JUAN R',
            'match_name' => 'DELACRUZJUANR',
        ], $overrides));
    }

    private function transaction(Client $client, array $overrides = []): Transaction
    {
        return Transaction::query()->create(array_merge([
            'client_id' => $client->id,
            'program' => 'CEAP',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-01-10',
            'status' => 'PAID',
            'amount' => 5000,
            'semester' => '1ST SEMESTER',
            'school_year' => '2025 - 2026',
        ], $overrides));
    }

    private function payload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->id,
            'mobile_no' => '09991234567',
            'email' => 'juan@example.com',
            'birthdate' => '1990-05-15',
            'sex' => 'MALE',
            'civil_status' => 'MARRIED',
            'age' => 36,
            'occupation' => 'teacher',
            'pwd' => 'NO',
            'school' => 'benguet state university',
            'college_department' => 'college of education',
            'course' => 'bachelor of secondary education',
            'year_level' => '1ST YEAR',
            'is_regular' => '1',
        ], $overrides);
    }

    public function test_self_service_page_renders_publicly(): void
    {
        $this->get(route('grantee-update.self-service'))
            ->assertOk()
            ->assertSee('Scholarship Grantee Self-Update');
    }

    public function test_store_updates_client_upserts_scholar_and_writes_log(): void
    {
        $client = $this->client();
        $this->transaction($client, ['program' => 'CEAP']);
        $scholar = ScholarInfo::query()->create([
            'client_id' => $client->id,
            'full_name' => 'DELA CRUZ, JUAN R',
            'program' => 'CEAP',
            'school' => 'OLD SCHOOL',
            'year_level' => '2ND YEAR',
            'is_regular' => 0,
            'year_started' => '',
            'landbank_no' => '',
        ]);

        $response = $this->post(route('grantee-update.store'), $this->payload($client))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbl_clients', [
            'id' => $client->id,
            'mobile_no' => '09991234567',
            'occupation' => 'TEACHER',
            'civil_status' => 'MARRIED',
            'pwd' => 'NO',
            'ip' => 'NO',
        ]);

        $this->assertDatabaseHas('tbl_scholar_info', [
            'id' => $scholar->id,
            'client_id' => $client->id,
            'program' => 'CEAP',
            'school' => 'BENGUET STATE UNIVERSITY',
            'college_department' => 'COLLEGE OF EDUCATION',
            'course' => 'BACHELOR OF SECONDARY EDUCATION',
            'year_level' => '1ST YEAR',
            'is_regular' => 1,
        ]);

        $this->assertDatabaseHas('tbl_update_logs', [
            'client_id' => $client->id,
            'full_name' => 'JUAN R DELA CRUZ',
            'ip_address' => '127.0.0.1',
            'action' => 'Grantee self-updated their information.',
        ]);

        $this->assertSame(1, UpdateLog::query()->count());
        $response->assertOk();
    }

    public function test_store_preserves_uneditable_name_and_location_fields(): void
    {
        $client = $this->client();
        $this->transaction($client);

        $this->post(route('grantee-update.store'), $this->payload($client, [
            'lastname' => 'HACKED',
            'firstname' => 'MALICIOUS',
            'city_municipality' => '999',
            'barangay' => '999',
        ]))->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbl_clients', [
            'id' => $client->id,
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'city_municipality' => $client->city_municipality,
            'barangay' => $client->barangay,
        ]);
    }

    public function test_store_inserts_scholar_row_with_comma_full_name(): void
    {
        $client = $this->client();
        $this->transaction($client, ['program' => 'OTEA']);

        $this->post(route('grantee-update.store'), $this->payload($client))
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tbl_scholar_info', [
            'client_id' => $client->id,
            'program' => 'OTEA',
            'full_name' => 'DELA CRUZ, JUAN R',
        ]);
    }

    public function test_store_rejects_missing_client_and_no_qualifying_program(): void
    {
        $this->post(route('grantee-update.store'), ['client_id' => 0])
            ->assertJson(['success' => false, 'message' => 'Missing client id']);

        $this->post(route('grantee-update.store'), ['client_id' => 999999])
            ->assertJson(['success' => false, 'message' => 'Client not found']);

        $client = $this->client();
        $this->transaction($client, ['program' => 'AICS']);

        $this->post(route('grantee-update.store'), $this->payload($client))
            ->assertJson(['success' => false, 'message' => 'No qualifying scholarship transaction found for this client.']);

        $this->assertSame(0, UpdateLog::query()->count());
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        $client = $this->client();
        $this->transaction($client);

        $this->post(route('grantee-update.store'), $this->payload($client, ['mobile_no' => '']))
            ->assertJson(['success' => false, 'message' => "Field 'mobile_no' is required."]);

        $this->assertSame(0, UpdateLog::query()->count());
    }

    public function test_logs_screen_is_gated_and_renders_rows(): void
    {
        $this->get(route('update-logs.index'))->assertRedirect(route('login'));

        $this->logInAs($this->pageUser('other_page.php'));
        $this->get(route('update-logs.index'), ['Accept' => 'application/json'])->assertForbidden();

        $this->logInAs($this->pageUser('update_logs.php'));
        $this->get(route('update-logs.index'))->assertOk()->assertSee('Grantee Update Logs');
    }

    public function test_logs_screen_formats_names_and_converts_to_philippine_time(): void
    {
        $client = $this->client();
        $this->transaction($client);
        $this->post(route('grantee-update.store'), $this->payload($client));

        $this->logInAs($this->pageUser('update_logs.php'));

        $response = $this->get(route('update-logs.index'));
        $response->assertOk()
            ->assertSee('CRUZ, JUAN R DELA')
            ->assertSee('VIGAN')
            ->assertSee('Grantee self-updated their information.')
            ->assertSee('127.0.0.1');
        $this->assertMatchesRegularExpression(
            '/\d{2}\/\d{2}\/\d{4} - \d{1,2}:\d{2} (AM|PM)/',
            $response->getContent()
        );
    }

    public function test_mobile_verify_endpoint_is_public(): void
    {
        $client = $this->client();

        $this->get(route('grantee.verify-mobile', ['id' => $client->id, 'mobile_no' => '09171234567']))
            ->assertOk()
            ->assertJson(['success' => true, 'skipped' => false]);

        $this->get(route('grantee.verify-mobile', ['id' => $client->id, 'mobile_no' => '09170000000']))
            ->assertJson(['success' => false]);

        $noMobile = $this->client(['mobile_no' => '']);

        $this->get(route('grantee.verify-mobile', ['id' => $noMobile->id, 'mobile_no' => '']))
            ->assertJson(['success' => true, 'skipped' => true]);
    }

    public function test_barangays_endpoint_is_public(): void
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN', 'code' => 'VIG']);
        Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'BARANGAY I']);

        $this->get(route('grantee.barangays', ['municipality_id' => $municipality->id]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJson([['id' => $municipality->barangays()->first()->id, 'name' => 'BARANGAY I']]);
    }
}
