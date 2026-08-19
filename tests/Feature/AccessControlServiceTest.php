<?php

namespace Tests\Feature;

use App\Models\ActionPermission;
use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserMunicipality;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessControlServiceTest extends TestCase
{
    use RefreshDatabase;

    private function enforce(string $pageName): void
    {
        $pages = config('authorization.pages');
        $pages[$pageName]['enforcement'] = true;
        config(['authorization.pages' => $pages]);
    }

    private function service(): AccessControlService
    {
        return app(AccessControlService::class);
    }

    private function user(array $pages = []): User
    {
        $user = User::factory()->create();

        foreach ($pages as $page) {
            Permission::query()->create([
                'user_id' => $user->id,
                'page_name' => $page,
                'can_access' => true,
            ]);
        }

        return $user;
    }

    private function place(string $name): Municipality
    {
        return Municipality::query()->create(['name' => $name, 'code' => strtoupper(substr($name, 0, 3))]);
    }

    public function test_can_access_action_truth_table(): void
    {
        $pageUser = $this->user(['clients.php']);

        // Non-adopted page → true regardless of rows.
        $this->assertTrue($this->service()->canAccessAction($pageUser, 'nobody_knows.php', 'create'));

        // Adopted but not enforcing → true (S2 default).
        $this->assertTrue($this->service()->canAccessAction($pageUser, 'clients.php', 'create'));

        // Enforced, VIEW = the page row.
        $this->enforce('clients.php');
        $this->app->forgetInstance(AccessControlService::class);
        $this->assertTrue($this->service()->canAccessAction($pageUser->fresh(), 'clients.php', 'VIEW'));

        // Enforced, other action without row → false.
        $this->assertFalse($this->service()->canAccessAction($pageUser->fresh(), 'clients.php', 'create'));

        // Enforced, row present → true.
        ActionPermission::query()->create([
            'user_id' => $pageUser->id,
            'page_name' => 'clients.php',
            'action' => 'CREATE',
        ]);
        $this->app->forgetInstance(AccessControlService::class);
        $this->assertTrue($this->service()->canAccessAction($pageUser->fresh(), 'clients.php', 'CREATE'));

        // '*' → true with no rows.
        $admin = $this->user(['*']);
        $this->assertTrue($this->service()->canAccessAction($admin, 'clients.php', 'create'));
    }

    public function test_permitted_actions_respects_enforcement_and_rows(): void
    {
        $pageUser = $this->user(['clients.php']);

        // Not enforcing → the full page catalog in config order.
        $this->assertSame(['VIEW', 'CREATE', 'EDIT', 'DELETE'], $this->service()->permittedActions($pageUser, 'clients.php'));

        $this->enforce('clients.php');
        $this->app->forgetInstance(AccessControlService::class);

        ActionPermission::query()->create([
            'user_id' => $pageUser->id,
            'page_name' => 'clients.php',
            'action' => 'DELETE',
        ]);

        // VIEW stays (the page row) plus the granted DELETE.
        $this->assertSame(['VIEW', 'DELETE'], $this->service()->permittedActions($pageUser->fresh(), 'clients.php'));

        // Non-adopted page → nothing to manage.
        $this->assertSame([], $this->service()->permittedActions($pageUser->fresh(), 'nobody_knows.php'));
    }

    public function test_effective_municipality_ids(): void
    {
        $muniA = $this->place('VIGAN');
        $muniB = $this->place('CANDON');

        $explicit = $this->user();
        UserMunicipality::query()->create(['user_id' => $explicit->id, 'municipality_id' => $muniA->id]);
        UserMunicipality::query()->create(['user_id' => $explicit->id, 'municipality_id' => $muniB->id]);
        $this->assertSame([$muniA->id, $muniB->id], $this->service()->effectiveMunicipalityIds($explicit));

        $all = $this->user();
        UserMunicipality::query()->create(['user_id' => $all->id, 'municipality_id' => AccessControlService::ALL_MUNICIPALITY_MARKER]);
        $this->assertSame([$muniA->id, $muniB->id], $this->service()->effectiveMunicipalityIds($all));

        $none = $this->user();
        $this->assertSame([], $this->service()->effectiveMunicipalityIds($none));

        $super = $this->user(['*']);
        $this->assertSame([$muniA->id, $muniB->id], $this->service()->effectiveMunicipalityIds($super));
    }

    public function test_has_all_municipalities_and_marker_distinct_from_star(): void
    {
        $all = $this->user();
        UserMunicipality::query()->create(['user_id' => $all->id, 'municipality_id' => AccessControlService::ALL_MUNICIPALITY_MARKER]);

        $this->assertTrue($this->service()->hasAllMunicipalities($all));
        $this->assertFalse($this->service()->isSuperAdmin($all));

        $super = $this->user(['*']);
        $this->assertTrue($this->service()->isSuperAdmin($super));
        $this->assertFalse($this->service()->hasAllMunicipalities($super));
    }

    public function test_can_access_record_truth_table(): void
    {
        $muniA = $this->place('VIGAN');
        $muniB = $this->place('CANDON');

        $explicit = $this->user();
        UserMunicipality::query()->create(['user_id' => $explicit->id, 'municipality_id' => $muniA->id]);

        // Enforcement off → inert for every municipality.
        $this->assertTrue($this->service()->canAccessRecord($explicit, $muniA->id, 'clients.php'));
        $this->assertTrue($this->service()->canAccessRecord($explicit, $muniB->id, 'clients.php'));

        // Page key null → strict (always enforced).
        $this->assertFalse($this->service()->canAccessRecord($explicit, $muniB->id));

        // Non-adopted page key → inert (page not in config).
        $this->assertTrue($this->service()->canAccessRecord($explicit, $muniB->id, 'nobody_knows.php'));

        $this->enforce('clients.php');
        $this->assertTrue($this->service()->canAccessRecord($explicit, $muniA->id, 'clients.php'));
        $this->assertFalse($this->service()->canAccessRecord($explicit, $muniB->id, 'clients.php'));
    }

    public function test_apply_municipality_scope_injects_where_in(): void
    {
        $muniA = $this->place('VIGAN');
        $muniB = $this->place('CANDON');
        $barA = Barangay::query()->create(['municipality_id' => $muniA->id, 'name' => 'BARANGAY I']);
        $barB = Barangay::query()->create(['municipality_id' => $muniB->id, 'name' => 'BARANGAY I']);

        foreach ([[$muniA->id, $barA->id], [$muniB->id, $barB->id]] as [$mid, $bid]) {
            Client::query()->create([
                'lastname' => 'DELA CRUZ',
                'firstname' => 'JUAN',
                'middlename' => 'R',
                'city_municipality' => $mid,
                'barangay' => $bid,
                'birthdate' => '1990-05-15',
                'age' => 36,
                'sex' => 'MALE',
                'civil_status' => 'SINGLE',
                'category' => 'ADULT (30-59)',
                'aff_org' => '',
                'full_name' => 'DELA CRUZ, JUAN R',
                'match_name' => 'DELACRUZJUANR',
            ]);
        }

        $scoped = $this->user();
        UserMunicipality::query()->create(['user_id' => $scoped->id, 'municipality_id' => $muniA->id]);

        $query = DB::table('tbl_clients');
        $this->service()->applyMunicipalityScope($query, $scoped, 'city_municipality', 'clients.php');
        $this->assertSame(2, (clone $query)->count());

        $this->enforce('clients.php');
        $this->app->forgetInstance(AccessControlService::class);

        $query = DB::table('tbl_clients');
        $this->service()->applyMunicipalityScope($query, $scoped->fresh(), 'city_municipality', 'clients.php');
        $this->assertSame(1, (clone $query)->count());
    }
}
