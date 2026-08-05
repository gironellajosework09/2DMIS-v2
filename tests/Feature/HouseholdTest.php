<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Household;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function householdUser(): User
    {
        $user = User::factory()->create(['username' => 'clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'household.php',
            'can_access' => true,
        ]);

        return $user;
    }

    private function headClient(): Client
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN', 'code' => 'VIG']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'BARANGAY I']);

        return Client::query()->create([
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'city_municipality' => $municipality->id,
            'barangay' => $barangay->id,
            'birthdate' => '1990-05-15',
            'age' => 36,
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'category' => 'ADULT (30-59)',
            'aff_org' => '',
            'full_name' => 'DELA CRUZ, JUAN',
            'match_name' => 'DELACRUZJUAN',
        ]);
    }

    public function test_households_page_is_gated_by_permission(): void
    {
        $this->logInAs(User::factory()->create(['username' => 'clerk']));

        $this->get(route('households.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    public function test_households_pages_load_for_permitted_user(): void
    {
        $head = $this->headClient();
        $household = Household::query()->create(['household_id' => 'VIG-00001', 'head_household' => $head->id]);

        $this->logInAs($this->householdUser());

        $this->get(route('households.index'))->assertOk();
        $this->get(route('households.create'))->assertOk();
        $this->get(route('households.show', $household))->assertOk();
    }

    public function test_household_can_be_created_with_generated_code_and_audit(): void
    {
        $head = $this->headClient();

        $this->logInAs($this->householdUser());

        $this->post(route('households.store'), ['head_household' => $head->id])
            ->assertRedirect(route('households.index'));

        $household = Household::query()->firstOrFail();

        $this->assertSame('VIG-00001', $household->household_id);
        $this->assertSame($head->id, $household->head_household);

        $audit = DB::table('tbl_audit_logs')->where('action', 'ADD_HOUSEHOLD')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('VIG-00001', (string) $audit->new_value);
    }

    public function test_head_cannot_be_used_twice_and_validation_errors(): void
    {
        $head = $this->headClient();
        Household::query()->create(['household_id' => 'VIG-00001', 'head_household' => $head->id]);

        $this->logInAs($this->householdUser());

        $this->post(route('households.store'), ['head_household' => $head->id])
            ->assertSessionHasErrors('head_household');

        $this->post(route('households.store'), ['head_household' => 0])
            ->assertSessionHasErrors('head_household');

        $this->assertSame(1, Household::query()->count());
    }

    public function test_household_can_be_deleted_with_members_unassigned(): void
    {
        $head = $this->headClient();
        $household = Household::query()->create(['household_id' => 'VIG-00001', 'head_household' => $head->id]);

        $member = Client::query()->create([
            'lastname' => 'SANTOS',
            'firstname' => 'MARIA',
            'city_municipality' => $head->city_municipality,
            'barangay' => $head->barangay,
            'household_id' => $household->id,
            'birthdate' => '1992-01-01',
            'age' => 34,
            'sex' => 'FEMALE',
            'civil_status' => 'SINGLE',
            'category' => 'ADULT (30-59)',
            'aff_org' => '',
            'full_name' => 'SANTOS, MARIA',
            'match_name' => 'SANTOSMARIA',
        ]);

        $this->logInAs($this->householdUser());

        $this->post(route('households.destroy', $household), [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNull($member->fresh()->household_id);
        $this->assertNull(Household::query()->find($household->id));

        $audit = DB::table('tbl_audit_logs')->where('action', 'DELETE_HOUSEHOLD')->first();
        $this->assertNotNull($audit);
    }

    public function test_household_data_feed_and_search(): void
    {
        $head = $this->headClient();
        Household::query()->create(['household_id' => 'VIG-00001', 'head_household' => $head->id]);

        $this->logInAs($this->householdUser());

        $this->post(route('households.data'), ['draw' => 1])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.head_name', 'DELA CRUZ, JUAN');

        $this->get(route('households.search').'?q=VIG')
            ->assertOk()
            ->assertJsonCount(1);

        $this->get(route('households.clients.search').'?q=JUAN')
            ->assertOk()
            ->assertJsonCount(0);

        $this->get(route('households.clients.options', $head))
            ->assertOk()
            ->assertJsonPath('full_name', 'DELA CRUZ, JUAN');
    }

    public function test_client_profile_and_mobile_verification(): void
    {
        $head = $this->headClient();
        $head->mobile_no = '09171234567';
        $head->save();

        $user = $this->householdUser();
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'clients.php',
            'can_access' => true,
        ]);

        $this->logInAs($user);

        $this->get(route('clients.show', $head))->assertOk();

        $this->get(route('clients.verify-mobile', ['id' => $head->id, 'mobile_no' => '09171234567']))
            ->assertOk()
            ->assertJson(['success' => true, 'skipped' => false]);

        $this->get(route('clients.verify-mobile', ['id' => $head->id, 'mobile_no' => '00000000000']))
            ->assertOk()
            ->assertJson(['success' => false, 'error' => 'Mobile number does not match']);

        $head->mobile_no = null;
        $head->save();

        $this->get(route('clients.verify-mobile', ['id' => $head->id, 'mobile_no' => '']))
            ->assertOk()
            ->assertJson(['success' => true, 'skipped' => true]);
    }
}
