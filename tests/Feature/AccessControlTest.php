<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\ProgramPermission;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    public function test_super_admin_can_access_gated_page(): void
    {
        $admin = User::factory()->create(['username' => 'boss']);
        Permission::query()->create([
            'user_id' => $admin->id,
            'page_name' => AccessControlService::SUPER_ADMIN_PAGE,
            'can_access' => true,
        ]);

        $this->logInAs($admin);

        $this->get(route('session.online'))->assertOk();
    }

    public function test_user_with_page_permission_can_access_gated_page(): void
    {
        $user = User::factory()->create(['username' => 'clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'currently_logged_users.php',
            'can_access' => true,
        ]);

        $this->logInAs($user);

        $this->get(route('session.online'))->assertOk();
    }

    public function test_user_without_permission_is_blocked(): void
    {
        $user = User::factory()->create(['username' => 'clerk']);

        $this->logInAs($user);

        $this->get(route('session.online'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    /**
     * v1 currently_logged_users.php semantics: only users with a session_token
     * whose last_activity falls inside the 20-minute window are listed. The
     * legacy username exclusion ('jordi', 'super_admin') is expressed as the
     * '*' super-admin permission row.
     */
    public function test_online_users_listing_shows_only_currently_active_sessions(): void
    {
        $viewer = User::factory()->create(['username' => 'watcher']);
        Permission::query()->create([
            'user_id' => $viewer->id,
            'page_name' => 'currently_logged_users.php',
            'can_access' => true,
        ]);

        $online = User::factory()->create(['username' => 'clerk_online']);
        $online->session_token = 'tok-online';
        $online->last_activity = now()->subMinutes(5);
        $online->save();

        $stale = User::factory()->create(['username' => 'clerk_stale']);
        $stale->session_token = 'tok-stale';
        $stale->last_activity = now()->subMinutes(21);
        $stale->save();

        $offline = User::factory()->create(['username' => 'clerk_offline']);
        $offline->last_activity = now();
        $offline->save();

        $admin = User::factory()->create(['username' => 'the_boss']);
        Permission::query()->create([
            'user_id' => $admin->id,
            'page_name' => AccessControlService::SUPER_ADMIN_PAGE,
            'can_access' => true,
        ]);
        $admin->session_token = 'tok-admin';
        $admin->last_activity = now();
        $admin->save();

        $this->logInAs($viewer);

        $this->get(route('session.online'))
            ->assertOk()
            ->assertSee('clerk_online')
            ->assertSee('Online')
            ->assertDontSee('clerk_stale')
            ->assertDontSee('clerk_offline')
            ->assertDontSee('the_boss');
    }

    public function test_super_admin_is_single_device_exempt(): void
    {
        $admin = User::factory()->create(['username' => 'boss']);
        Permission::query()->create([
            'user_id' => $admin->id,
            'page_name' => AccessControlService::SUPER_ADMIN_PAGE,
            'can_access' => true,
        ]);

        $this->withSession(['session_token' => 'some-other-token'])->actingAs($admin);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_program_permission_gate(): void
    {
        $user = User::factory()->create(['username' => 'clerk']);

        $this->assertFalse(Gate::forUser($user)->allows('program', 'AICS'));

        ProgramPermission::query()->create([
            'user_id' => $user->id,
            'program_name' => 'AICS',
        ]);

        // Simulate the next HTTP request so the service re-reads permissions.
        $this->app->forgetInstance(AccessControlService::class);

        $this->assertTrue(Gate::forUser($user)->allows('program', 'AICS'));
    }

    public function test_super_admin_bypasses_program_gate(): void
    {
        $admin = User::factory()->create(['username' => 'boss']);
        Permission::query()->create([
            'user_id' => $admin->id,
            'page_name' => AccessControlService::SUPER_ADMIN_PAGE,
            'can_access' => true,
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('program', 'TUPAD'));
    }
}
