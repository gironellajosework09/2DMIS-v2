<?php

namespace Tests\Feature;

use App\Models\ActionPermission;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserMunicipality;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ActionPermissionTest extends TestCase
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

    private function pageUser(string $page = 'clients.php'): User
    {
        $user = User::factory()->create(['username' => 'clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => $page,
            'can_access' => true,
        ]);
        $this->logInAs($user);

        return $user;
    }

    private function validClientPayload(int $municipalityId, int $barangayId): array
    {
        return [
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'middlename' => 'R',
            'city_municipality' => $municipalityId,
            'barangay' => $barangayId,
            'birthdate' => '1990-05-15',
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'pwd' => 'NO',
            'ip' => 'NO',
            'aff_org' => [],
        ];
    }

    private function place(): array
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN', 'code' => 'VIG']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'BARANGAY I']);

        return [$municipality, $barangay];
    }

    public function test_action_gate_is_inert_until_enforcement(): void
    {
        $this->pageUser();
        [$municipality, $barangay] = $this->place();

        $this->post(route('clients.store'), $this->validClientPayload($municipality->id, $barangay->id))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseCount('tbl_clients', 1);
    }

    public function test_action_middleware_denies_without_row_when_enforced(): void
    {
        $this->enforce('clients.php');

        $this->pageUser();
        [$municipality, $barangay] = $this->place();

        $this->post(route('clients.store'), $this->validClientPayload($municipality->id, $barangay->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');

        $this->assertDatabaseCount('tbl_clients', 0);
    }

    public function test_action_middleware_denies_json_with_403_when_enforced(): void
    {
        $this->enforce('clients.php');

        $this->pageUser();
        [$municipality, $barangay] = $this->place();

        $this->postJson(route('clients.store'), $this->validClientPayload($municipality->id, $barangay->id))
            ->assertForbidden();

        $this->assertDatabaseCount('tbl_clients', 0);
    }

    public function test_action_row_grants_access_when_enforced(): void
    {
        $this->enforce('clients.php');

        $user = $this->pageUser();
        ActionPermission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'clients.php',
            'action' => 'CREATE',
        ]);
        UserMunicipality::query()->create([
            'user_id' => $user->id,
            'municipality_id' => AccessControlService::ALL_MUNICIPALITY_MARKER,
        ]);

        [$municipality, $barangay] = $this->place();

        $this->post(route('clients.store'), $this->validClientPayload($municipality->id, $barangay->id))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseCount('tbl_clients', 1);
    }

    public function test_super_admin_bypasses_action_gate(): void
    {
        $this->enforce('clients.php');

        $admin = User::factory()->create(['username' => 'boss']);
        Permission::query()->create([
            'user_id' => $admin->id,
            'page_name' => AccessControlService::SUPER_ADMIN_PAGE,
            'can_access' => true,
        ]);
        $this->logInAs($admin);

        [$municipality, $barangay] = $this->place();

        $this->post(route('clients.store'), $this->validClientPayload($municipality->id, $barangay->id))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseCount('tbl_clients', 1);
    }

    public function test_view_is_the_page_row_itself(): void
    {
        $this->enforce('clients.php');

        $user = $this->pageUser();

        $this->get(route('clients.create'))->assertOk();

        $this->app->forgetInstance(AccessControlService::class);

        $this->assertTrue(
            app(AccessControlService::class)->canAccessAction($user->fresh(), 'clients.php', 'VIEW')
        );
    }

    public function test_unknown_action_fails_closed_when_enforced(): void
    {
        $this->enforce('clients.php');

        $user = $this->pageUser();

        $this->assertFalse(
            app(AccessControlService::class)->canAccessAction($user, 'clients.php', 'SCAN')
        );
        $this->assertFalse(
            app(AccessControlService::class)->canAccessAction($user, 'clients.php', 'MANAGE')
        );
    }

    public function test_flipping_enforcement_off_restores_prior_behavior(): void
    {
        $this->enforce('clients.php');

        $user = $this->pageUser();
        [$municipality, $barangay] = $this->place();

        $this->post(route('clients.store'), $this->validClientPayload($municipality->id, $barangay->id))
            ->assertRedirect(route('dashboard'));

        // S2 rollback: flip the flag off and the denied user is allowed again.
        $pages = config('authorization.pages');
        $pages['clients.php']['enforcement'] = false;
        config(['authorization.pages' => $pages]);

        $this->post(route('clients.store'), $this->validClientPayload($municipality->id, $barangay->id))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseCount('tbl_clients', 1);
    }

    public function test_action_gate_is_registered(): void
    {
        $this->enforce('clients.php');

        $user = $this->pageUser();
        ActionPermission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'clients.php',
            'action' => 'CREATE',
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('action', ['clients.php', 'CREATE']));
        $this->assertFalse(Gate::forUser($user)->allows('action', ['clients.php', 'DELETE']));
    }

    public function test_export_action_gate_blocks_and_grants(): void
    {
        $this->enforce('all_transactions.php');

        $user = $this->pageUser('all_transactions.php');

        $this->get(route('transactions.export'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');

        ActionPermission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'all_transactions.php',
            'action' => 'EXPORT',
        ]);

        $this->app->forgetInstance(AccessControlService::class);

        $this->get(route('transactions.export'))->assertOk();
    }
}
