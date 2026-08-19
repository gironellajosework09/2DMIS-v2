<?php

namespace Tests\Feature;

use App\Models\ActionPermission;
use App\Models\Barangay;
use App\Models\Client;
use App\Models\Household;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\ScholarInfo;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserMunicipality;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeTest extends TestCase
{
    use RefreshDatabase;

    private function enforce(string $pageName): void
    {
        $pages = config('authorization.pages');
        $pages[$pageName]['enforcement'] = true;
        config(['authorization.pages' => $pages]);
    }

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function grantPage(User $user, string $page): void
    {
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => $page,
            'can_access' => true,
        ]);
    }

    private function grantAction(User $user, string $page, string $action): void
    {
        ActionPermission::query()->create([
            'user_id' => $user->id,
            'page_name' => $page,
            'action' => $action,
        ]);
    }

    private function scopeUser(string $page, array $municipalityIds): User
    {
        $user = User::factory()->create(['username' => 'scope-clerk']);
        $this->grantPage($user, $page);

        foreach ($municipalityIds as $id) {
            UserMunicipality::query()->create(['user_id' => $user->id, 'municipality_id' => $id]);
        }

        $this->logInAs($user);

        return $user;
    }

    private function place(string $name): array
    {
        $municipality = Municipality::query()->create(['name' => $name, 'code' => strtoupper(substr($name, 0, 3))]);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'BARANGAY I']);

        return [$municipality, $barangay];
    }

    private function client(int $municipalityId, int $barangayId): Client
    {
        return Client::query()->create([
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'middlename' => 'R',
            'city_municipality' => $municipalityId,
            'barangay' => $barangayId,
            'birthdate' => '1990-05-15',
            'age' => 36,
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'category' => 'ADULT (30-59)',
            'aff_org' => '',
            'mobile_no' => '09171234567',
            'full_name' => 'DELA CRUZ, JUAN R',
            'match_name' => 'DELACRUZJUANR',
        ]);
    }

    public function test_feed_is_unscoped_before_enforcement(): void
    {
        $this->scopeUser('clients.php', []);
        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $this->client($muniA->id, $barA->id);
        $this->client($muniB->id, $barB->id);

        $json = $this->postJson(route('clients.data'))->assertOk()->json();

        $this->assertSame(2, $json['recordsTotal']);
        $this->assertCount(2, $json['data']);
    }

    public function test_feed_scopes_to_granted_municipalities_when_enforced(): void
    {
        $this->enforce('clients.php');

        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $this->scopeUser('clients.php', [$muniA->id]);
        $clientA = $this->client($muniA->id, $barA->id);
        $this->client($muniB->id, $barB->id);

        $json = $this->postJson(route('clients.data'))->assertOk()->json();

        $this->assertSame(1, $json['recordsTotal']);
        $this->assertCount(1, $json['data']);
        $this->assertSame($clientA->id, (int) $json['data'][0]['id']);
    }

    public function test_feed_fails_closed_with_no_scope_rows(): void
    {
        $this->enforce('clients.php');

        [$muniA, $barA] = $this->place('VIGAN');
        $this->scopeUser('clients.php', []);
        $this->client($muniA->id, $barA->id);

        $json = $this->postJson(route('clients.data'))->assertOk()->json();

        $this->assertSame(0, $json['recordsTotal']);
        $this->assertSame([], $json['data']);
    }

    public function test_all_marker_grants_every_municipality(): void
    {
        $this->enforce('clients.php');

        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $this->scopeUser('clients.php', [AccessControlService::ALL_MUNICIPALITY_MARKER]);
        $this->client($muniA->id, $barA->id);
        $this->client($muniB->id, $barB->id);

        $json = $this->postJson(route('clients.data'))->assertOk()->json();

        $this->assertSame(2, $json['recordsTotal']);
        $this->assertCount(2, $json['data']);
    }

    public function test_super_admin_sees_every_municipality(): void
    {
        $this->enforce('clients.php');

        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $this->client($muniA->id, $barA->id);
        $this->client($muniB->id, $barB->id);

        $admin = User::factory()->create(['username' => 'boss']);
        $this->grantPage($admin, '*');
        $this->logInAs($admin);

        $json = $this->postJson(route('clients.data'))->assertOk()->json();

        $this->assertSame(2, $json['recordsTotal']);
        $this->assertCount(2, $json['data']);
    }

    public function test_record_check_blocks_out_of_scope_client_show(): void
    {
        $this->enforce('clients.php');

        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $this->scopeUser('clients.php', [$muniA->id]);
        $this->client($muniA->id, $barA->id);
        $clientB = $this->client($muniB->id, $barB->id);

        $this->get(route('clients.show', $clientB->id))->assertForbidden();
    }

    public function test_record_check_blocks_out_of_scope_client_destroy(): void
    {
        $this->enforce('clients.php');

        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $user = $this->scopeUser('clients.php', [$muniA->id]);
        $this->grantAction($user, 'clients.php', 'DELETE');
        $this->client($muniA->id, $barA->id);
        $clientB = $this->client($muniB->id, $barB->id);

        $this->post(route('clients.destroy', $clientB->id))->assertForbidden();

        $this->assertDatabaseHas('tbl_clients', ['id' => $clientB->id]);
    }

    public function test_store_blocks_client_in_out_of_scope_municipality(): void
    {
        $this->enforce('clients.php');

        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $user = $this->scopeUser('clients.php', [$muniA->id]);
        $this->grantAction($user, 'clients.php', 'CREATE');

        $this->post(route('clients.store'), [
            'lastname' => 'SANTOS',
            'firstname' => 'MARIA',
            'city_municipality' => $muniB->id,
            'barangay' => $barB->id,
            'birthdate' => '1992-01-01',
            'sex' => 'FEMALE',
            'civil_status' => 'SINGLE',
            'pwd' => 'NO',
            'ip' => 'NO',
        ])->assertForbidden();

        $this->assertDatabaseCount('tbl_clients', 0);
    }

    public function test_transaction_feed_scopes_by_client_municipality(): void
    {
        $this->enforce('all_transactions.php');

        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $this->scopeUser('all_transactions.php', [$muniA->id]);
        $clientA = $this->client($muniA->id, $barA->id);
        $clientB = $this->client($muniB->id, $barB->id);

        foreach (['A' => $clientA, 'B' => $clientB] as $key => $client) {
            Transaction::query()->create([
                'client_id' => $client->id,
                'program' => 'AICS',
                'patient_name' => $client->full_name,
                'date_applied' => '2026-08-01',
                'type' => 'OCA',
                'status' => 'PENDING PAYOUT',
            ]);
        }

        $json = $this->postJson(route('transactions.data'))->assertOk()->json();

        $this->assertSame(1, $json['recordsTotal']);
        $this->assertCount(1, $json['data']);
    }

    public function test_household_record_check_blocks_out_of_scope_destroy(): void
    {
        $this->enforce('household.php');

        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $user = $this->scopeUser('household.php', [$muniA->id]);
        $this->grantAction($user, 'household.php', 'DELETE');
        $this->client($muniA->id, $barA->id);
        $headB = $this->client($muniB->id, $barB->id);

        $household = Household::query()->create([
            'household_id' => 'VIG-00001',
            'head_household' => $headB->id,
        ]);

        $this->post(route('households.destroy', $household->id))->assertForbidden();

        $this->assertDatabaseHas('tbl_household', ['id' => $household->id]);
    }

    public function test_scholar_feed_scopes_by_client_municipality(): void
    {
        $this->enforce('scholars.php');

        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');
        $this->scopeUser('scholars.php', [$muniA->id]);
        $clientA = $this->client($muniA->id, $barA->id);
        $clientB = $this->client($muniB->id, $barB->id);

        ScholarInfo::query()->create([
            'client_id' => $clientA->id,
            'full_name' => $clientA->full_name,
            'program' => 'CEDSSG',
            'school' => 'SPA',
            'year_started' => '2024 - 2025',
            'landbank_no' => '1234',
        ]);
        ScholarInfo::query()->create([
            'client_id' => $clientB->id,
            'full_name' => $clientB->full_name,
            'program' => 'CEDSSG',
            'school' => 'SPA',
            'year_started' => '2024 - 2025',
            'landbank_no' => '5678',
        ]);

        $json = $this->postJson(route('scholars.data'))->assertOk()->json();

        $this->assertSame(1, $json['recordsTotal']);
        $this->assertCount(1, $json['data']);
    }
}
