<?php

namespace Tests\Feature;

use App\Models\ActionPermission;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserMunicipality;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationAdminTest extends TestCase
{
    use RefreshDatabase;

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

    private function manager(): User
    {
        $user = User::factory()->create(['username' => 'perm-admin']);
        $this->grantPage($user, 'manage_permissions.php');
        $this->logInAs($user);

        return $user;
    }

    private function place(string $name): Municipality
    {
        return Municipality::query()->create(['name' => $name, 'code' => strtoupper(substr($name, 0, 3))]);
    }

    public function test_action_permissions_screen_render(): void
    {
        $this->manager();
        $subject = User::factory()->create(['username' => 'subject']);

        $this->get(route('admin.permissions.actions', ['user_id' => $subject->id]))
            ->assertOk()
            ->assertSee('Manage Action Permissions')
            ->assertSee('value="clients.php:CREATE"', false)
            ->assertSee('value="all_transactions.php:EXPORT"', false)
            ->assertSee('value="register.php:CREATE"', false);
    }

    public function test_action_permissions_full_replace(): void
    {
        $this->manager();

        $subject = User::factory()->create(['username' => 'subject']);
        ActionPermission::query()->create([
            'user_id' => $subject->id,
            'page_name' => 'clients.php',
            'action' => 'DELETE',
        ]);

        $this->post(route('admin.permissions.update-actions', $subject->id), [
            'actions' => ['clients.php:CREATE', 'scholars.php:EDIT'],
        ])->assertRedirect();

        $this->assertSame(
            ['clients.php:CREATE', 'scholars.php:EDIT'],
            $subject->actionPermissions()
                ->get(['page_name', 'action'])
                ->map(fn (ActionPermission $row) => $row->page_name.':'.$row->action)
                ->sort()
                ->values()
                ->all()
        );

        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_ACTION_PERMISSIONS',
            'target_table' => 'tbl_action_permissions',
            'target_id' => $subject->id,
            'old_value' => json_encode(['username' => 'subject', 'actions' => ['clients.php:DELETE']]),
            'new_value' => json_encode(['username' => 'subject', 'actions' => ['clients.php:CREATE', 'scholars.php:EDIT']]),
        ]);
    }

    public function test_action_permissions_reject_unknown_composite(): void
    {
        $this->manager();

        $subject = User::factory()->create(['username' => 'subject']);

        $this->post(route('admin.permissions.update-actions', $subject->id), [
            'actions' => ['clients.php:not_an_action'],
        ])->assertSessionHasErrors('actions.0');

        $this->assertSame(0, $subject->actionPermissions()->count());
        $this->assertDatabaseMissing('tbl_audit_logs', ['action' => 'MANAGE_ACTION_PERMISSIONS']);
    }

    public function test_action_permissions_noop_writes_no_audit(): void
    {
        $this->manager();

        $subject = User::factory()->create(['username' => 'subject']);
        ActionPermission::query()->create([
            'user_id' => $subject->id,
            'page_name' => 'clients.php',
            'action' => 'CREATE',
        ]);

        $this->post(route('admin.permissions.update-actions', $subject->id), [
            'actions' => ['clients.php:CREATE'],
        ])->assertRedirect();

        $this->assertDatabaseMissing('tbl_audit_logs', ['action' => 'MANAGE_ACTION_PERMISSIONS']);
    }

    public function test_scope_screen_render(): void
    {
        $this->manager();
        $this->place('VIGAN');
        $subject = User::factory()->create(['username' => 'subject']);

        $this->get(route('admin.permissions.scopes', ['user_id' => $subject->id]))
            ->assertOk()
            ->assertSee('Manage Municipality Scope')
            ->assertSee('VIGAN');
    }

    public function test_scope_full_replace_with_all_marker(): void
    {
        $this->manager();
        $vigan = $this->place('VIGAN');

        $subject = User::factory()->create(['username' => 'subject']);
        UserMunicipality::query()->create(['user_id' => $subject->id, 'municipality_id' => $vigan->id]);

        $this->post(route('admin.permissions.update-scopes', $subject->id), [
            'municipalities' => [],
            'all' => true,
        ])->assertRedirect();

        $this->assertSame(
            [AccessControlService::ALL_MUNICIPALITY_MARKER],
            $subject->municipalityScope()->pluck('municipality_id')->map(fn ($id) => (int) $id)->all()
        );

        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_SCOPE_ASSIGNMENTS',
            'target_table' => 'tbl_user_municipalities',
            'target_id' => $subject->id,
            'old_value' => json_encode(['username' => 'subject', 'municipalities' => [$vigan->id]]),
            'new_value' => json_encode(['username' => 'subject', 'municipalities' => [AccessControlService::ALL_MUNICIPALITY_MARKER]]),
        ]);
    }

    public function test_scope_full_replace_with_municipalities(): void
    {
        $this->manager();
        $vigan = $this->place('VIGAN');
        $candon = $this->place('CANDON');

        $subject = User::factory()->create(['username' => 'subject']);
        UserMunicipality::query()->create(['user_id' => $subject->id, 'municipality_id' => AccessControlService::ALL_MUNICIPALITY_MARKER]);

        $this->post(route('admin.permissions.update-scopes', $subject->id), [
            'municipalities' => [$vigan->id, $candon->id],
            'all' => false,
        ])->assertRedirect();

        $this->assertSame(
            [$vigan->id, $candon->id],
            $subject->municipalityScope()->pluck('municipality_id')->map(fn ($id) => (int) $id)->sort()->values()->all()
        );
    }

    public function test_scope_reject_unknown_municipality(): void
    {
        $this->manager();
        $this->place('VIGAN');

        $subject = User::factory()->create(['username' => 'subject']);

        $this->post(route('admin.permissions.update-scopes', $subject->id), [
            'municipalities' => [999999],
        ])->assertSessionHasErrors('municipalities.0');

        $this->assertSame(0, $subject->municipalityScope()->count());
        $this->assertDatabaseMissing('tbl_audit_logs', ['action' => 'MANAGE_SCOPE_ASSIGNMENTS']);
    }

    public function test_scope_noop_writes_no_audit(): void
    {
        $this->manager();
        $vigan = $this->place('VIGAN');

        $subject = User::factory()->create(['username' => 'subject']);
        UserMunicipality::query()->create(['user_id' => $subject->id, 'municipality_id' => $vigan->id]);

        $this->post(route('admin.permissions.update-scopes', $subject->id), [
            'municipalities' => [$vigan->id],
        ])->assertRedirect();

        $this->assertDatabaseMissing('tbl_audit_logs', ['action' => 'MANAGE_SCOPE_ASSIGNMENTS']);
    }

    public function test_admin_screens_require_manage_permissions_page(): void
    {
        $user = User::factory()->create(['username' => 'clerk']);
        $this->logInAs($user);

        $this->get(route('admin.permissions.actions'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');

        $this->get(route('admin.permissions.scopes'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }
}
