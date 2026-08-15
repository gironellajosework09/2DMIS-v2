<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\MultiDeviceExemption;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\ProgramPermission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministrationTest extends TestCase
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

    private function manager(string $page): User
    {
        $user = User::factory()->create(['username' => 'admin-'.$page]);
        $this->grantPage($user, $page);
        $this->logInAs($user);

        return $user;
    }

    private function client(): Client
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN', 'code' => 'VIG']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'BARANGAY I']);

        return Client::query()->create([
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
        ]);
    }

    public function test_super_admin_can_reach_all_p7_screens(): void
    {
        $admin = $this->manager('register.php');
        $this->grantPage($admin, '*');

        $this->get(route('admin.users.create'))->assertOk();
        $this->get(route('admin.permissions.pages'))->assertOk();
        $this->get(route('admin.program-permissions.pages'))->assertOk();
        $this->get(route('admin.exemptions.pages'))->assertOk();
        $this->get(route('admin.audit-logs.index'))->assertOk();
    }

    public function test_user_with_page_row_reaches_only_own_screen(): void
    {
        $user = $this->manager('audit_logs.php');

        $this->get(route('admin.audit-logs.index'))->assertOk();
        $this->get(route('admin.permissions.pages'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    public function test_user_without_page_row_is_blocked(): void
    {
        $this->logInAs(User::factory()->create(['username' => 'clerk']));

        $this->get(route('admin.users.create'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    public function test_program_permission_does_not_grant_p7_screen(): void
    {
        $user = User::factory()->create(['username' => 'clerk']);
        ProgramPermission::query()->create(['user_id' => $user->id, 'program_name' => 'AICS']);
        $this->logInAs($user);

        $this->get(route('admin.audit-logs.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    public function test_audit_feed_without_page_gate_is_json_403(): void
    {
        $this->logInAs(User::factory()->create(['username' => 'clerk']));

        $this->postJson(route('admin.audit-logs.data'), ['table' => 'tbl_clients'])->assertForbidden();
        $this->postJson(route('admin.audit-logs.leaderboard'), ['table' => 'tbl_clients'])->assertForbidden();
    }

    public function test_user_creation_succeeds_and_audits(): void
    {
        $admin = $this->manager('register.php');

        $this->post(route('admin.users.store'), [
            'username' => 'newbie',
            'password' => 'plain-secret',
            'password_confirmation' => 'plain-secret',
        ])->assertRedirect();

        $newUser = User::query()->where('username', 'newbie')->firstOrFail();
        $this->assertTrue(Hash::check('plain-secret', $newUser->password));
        $this->assertSame(0, $newUser->permissions()->count());

        $this->assertDatabaseHas('tbl_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'MANAGE_USER_CREATE',
            'target_table' => 'tbl_users',
            'target_id' => $newUser->id,
            'new_value' => json_encode(['username' => 'newbie']),
        ]);
    }

    public function test_user_creation_rejects_duplicate_username_without_audit(): void
    {
        User::factory()->create(['username' => 'taken']);
        $this->manager('register.php');

        $this->post(route('admin.users.store'), [
            'username' => 'taken',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('tbl_audit_logs', ['action' => 'MANAGE_USER_CREATE']);
    }

    public function test_user_creation_requires_matching_confirmation(): void
    {
        $this->manager('register.php');

        $this->post(route('admin.users.store'), [
            'username' => 'newbie',
            'password' => 'secret123',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('tbl_users', ['username' => 'newbie']);
        $this->assertDatabaseMissing('tbl_audit_logs', ['action' => 'MANAGE_USER_CREATE']);
    }

    public function test_page_permissions_full_replace(): void
    {
        $admin = $this->manager('manage_permissions.php');
        $this->grantPage($admin, 'scholars.php');

        $subject = User::factory()->create(['username' => 'subject']);
        $this->grantPage($subject, 'clients.php');
        $this->grantPage($subject, 'household.php');

        $this->post(route('admin.permissions.update-pages', $subject->id), [
            'pages' => ['clients.php', 'scholars.php'],
            'super_admin' => false,
        ])->assertRedirect();

        $this->assertSame(
            ['clients.php', 'scholars.php'],
            $subject->permissions()->pluck('page_name')->all()
        );

        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_PAGE_PERMISSIONS',
            'target_table' => 'tbl_permissions',
            'target_id' => $subject->id,
            'old_value' => json_encode(['username' => 'subject', 'pages' => ['clients.php', 'household.php']]),
            'new_value' => json_encode(['username' => 'subject', 'pages' => ['clients.php', 'scholars.php']]),
        ]);
    }

    public function test_remove_all_page_permissions_is_allowed(): void
    {
        $this->manager('manage_permissions.php');

        $subject = User::factory()->create(['username' => 'subject']);
        $this->grantPage($subject, 'clients.php');

        $this->post(route('admin.permissions.update-pages', $subject->id), [
            'pages' => [],
            'super_admin' => false,
        ])->assertRedirect();

        $this->assertSame(0, $subject->permissions()->count());
        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_PAGE_PERMISSIONS',
            'target_id' => $subject->id,
            'new_value' => json_encode(['username' => 'subject', 'pages' => []]),
        ]);
    }

    public function test_page_permission_rejects_unknown_page_without_writes(): void
    {
        $this->manager('manage_permissions.php');

        $subject = User::factory()->create(['username' => 'subject']);
        $this->grantPage($subject, 'clients.php');

        $this->post(route('admin.permissions.update-pages', $subject->id), [
            'pages' => ['not_a_real_page.php'],
            'super_admin' => false,
        ])->assertSessionHasErrors('pages.0');

        $this->assertSame(['clients.php'], $subject->permissions()->pluck('page_name')->all());
        $this->assertDatabaseMissing('tbl_audit_logs', ['action' => 'MANAGE_PAGE_PERMISSIONS']);
    }

    public function test_star_grant_writes_super_admin_row_and_audits(): void
    {
        $admin = $this->manager('manage_permissions.php');
        $this->grantPage($admin, 'clients.php');

        $subject = User::factory()->create(['username' => 'subject']);

        $this->post(route('admin.permissions.update-pages', $subject->id), [
            'pages' => ['clients.php'],
            'super_admin' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('tbl_permissions', [
            'user_id' => $subject->id,
            'page_name' => '*',
            'can_access' => 1,
        ]);

        $this->app->forgetInstance(AccessControlService::class);
        $this->assertTrue(app(AccessControlService::class)->isSuperAdmin($subject->fresh()));

        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_SUPER_ADMIN_GRANT',
            'target_table' => 'tbl_permissions',
            'target_id' => $subject->id,
            'old_value' => json_encode(['username' => 'subject', 'super_admin' => false]),
            'new_value' => json_encode(['username' => 'subject', 'super_admin' => true]),
        ]);
        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_PAGE_PERMISSIONS',
            'target_table' => 'tbl_permissions',
            'target_id' => $subject->id,
            'new_value' => json_encode(['username' => 'subject', 'pages' => ['clients.php', '*']]),
        ]);
    }

    public function test_star_revoke_writes_super_admin_revoke_audit(): void
    {
        $this->manager('manage_permissions.php');

        $subject = User::factory()->create(['username' => 'subject']);
        $this->grantPage($subject, '*');
        $this->grantPage($subject, 'clients.php');

        $this->post(route('admin.permissions.update-pages', $subject->id), [
            'pages' => ['clients.php'],
            'super_admin' => false,
        ])->assertRedirect();

        $this->assertDatabaseMissing('tbl_permissions', ['user_id' => $subject->id, 'page_name' => '*']);

        $this->app->forgetInstance(AccessControlService::class);
        $this->assertFalse(app(AccessControlService::class)->isSuperAdmin($subject->fresh()));

        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_SUPER_ADMIN_REVOKE',
            'target_table' => 'tbl_permissions',
            'target_id' => $subject->id,
        ]);
    }

    public function test_page_grant_takes_effect_for_gate(): void
    {
        $this->manager('manage_permissions.php');

        $subject = User::factory()->create(['username' => 'subject']);

        $this->post(route('admin.permissions.update-pages', $subject->id), [
            'pages' => ['audit_logs.php'],
            'super_admin' => false,
        ])->assertRedirect();

        $this->app->forgetInstance(AccessControlService::class);
        $this->logInAs($subject);

        $this->get(route('admin.audit-logs.index'))->assertOk();
    }

    public function test_page_catalog_includes_p7_keys_and_star_toggle(): void
    {
        $admin = $this->manager('manage_permissions.php');

        $this->get(route('admin.permissions.pages', ['user_id' => $admin->id]))
            ->assertOk()
            ->assertSee('value="manage_permissions.php"', false)
            ->assertSee('value="register.php"', false)
            ->assertSee('value="audit_logs.php"', false)
            ->assertSee('value="manage_program_permissions.php"', false)
            ->assertSee('value="manage_multi_device_exemptions.php"', false)
            ->assertSee('name="super_admin"', false);
    }

    public function test_program_permissions_full_replace(): void
    {
        $admin = $this->manager('manage_program_permissions.php');

        $subject = User::factory()->create(['username' => 'subject']);
        ProgramPermission::query()->create(['user_id' => $subject->id, 'program_name' => 'AICS']);

        $this->post(route('admin.program-permissions.update', $subject->id), [
            'programs' => ['TUPAD', 'GIP'],
        ])->assertRedirect();

        $this->assertSame(
            ['GIP', 'TUPAD'],
            $subject->programPermissions()->pluck('program_name')->all()
        );

        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_PROGRAM_PERMISSIONS',
            'target_table' => 'tbl_program_permissions',
            'target_id' => $subject->id,
            'old_value' => json_encode(['username' => 'subject', 'programs' => ['AICS']]),
            'new_value' => json_encode(['username' => 'subject', 'programs' => ['GIP', 'TUPAD']]),
        ]);
    }

    public function test_program_permission_rejects_unknown_program_without_writes(): void
    {
        $this->manager('manage_program_permissions.php');

        $subject = User::factory()->create(['username' => 'subject']);

        $this->post(route('admin.program-permissions.update', $subject->id), [
            'programs' => ['NOT_A_PROGRAM'],
        ])->assertSessionHasErrors('programs.0');

        $this->assertSame(0, $subject->programPermissions()->count());
        $this->assertDatabaseMissing('tbl_audit_logs', ['action' => 'MANAGE_PROGRAM_PERMISSIONS']);
    }

    public function test_exemption_grant_and_revoke(): void
    {
        $admin = $this->manager('manage_multi_device_exemptions.php');

        $subject = User::factory()->create(['username' => 'subject']);

        $this->post(route('admin.exemptions.toggle', $subject->id), ['grant' => '1'])
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_multi_device_exemptions', ['user_id' => $subject->id]);
        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_EXEMPTION_GRANT',
            'target_table' => 'tbl_multi_device_exemptions',
            'target_id' => $subject->id,
            'new_value' => json_encode(['username' => 'subject']),
        ]);

        $this->app->forgetInstance(AccessControlService::class);

        $this->post(route('admin.exemptions.toggle', $subject->id), ['grant' => false])
            ->assertRedirect();

        $this->assertDatabaseMissing('tbl_multi_device_exemptions', ['user_id' => $subject->id]);
        $this->assertDatabaseHas('tbl_audit_logs', [
            'action' => 'MANAGE_EXEMPTION_REVOKE',
            'target_table' => 'tbl_multi_device_exemptions',
            'target_id' => $subject->id,
            'old_value' => json_encode(['username' => 'subject']),
        ]);
    }

    public function test_noop_exemption_toggle_writes_no_audit(): void
    {
        $this->manager('manage_multi_device_exemptions.php');

        $subject = User::factory()->create(['username' => 'subject']);
        MultiDeviceExemption::query()->create(['user_id' => $subject->id]);

        $this->post(route('admin.exemptions.toggle', $subject->id), ['grant' => '1'])
            ->assertRedirect();

        $this->assertDatabaseMissing('tbl_audit_logs', [
            'action' => 'MANAGE_EXEMPTION_GRANT',
            'target_id' => $subject->id,
        ]);
    }

    public function test_super_admin_is_excluded_from_picker_and_toggle_is_noop(): void
    {
        $admin = $this->manager('manage_multi_device_exemptions.php');

        $super = User::factory()->create(['username' => 'superstar']);
        $this->grantPage($super, '*');

        $this->get(route('admin.exemptions.pages'))
            ->assertOk()
            ->assertDontSee('superstar');

        $this->post(route('admin.exemptions.toggle', $super->id), ['grant' => '1'])
            ->assertRedirect();

        $this->assertDatabaseMissing('tbl_multi_device_exemptions', ['user_id' => $super->id]);
        $this->assertDatabaseMissing('tbl_audit_logs', ['action' => 'MANAGE_EXEMPTION_GRANT']);
    }

    public function test_audit_data_feed_returns_v1_contract_for_subject_table(): void
    {
        $this->manager('audit_logs.php');

        $actor = User::factory()->create(['username' => 'actor']);
        DB::table('tbl_audit_logs')->insert([
            'user_id' => $actor->id,
            'action' => 'LOGIN',
            'target_table' => 'tbl_users',
            'target_id' => $actor->id,
            'created_at' => '2026-08-15 10:00:00',
        ]);

        $json = $this->postJson(route('admin.audit-logs.data'), ['table' => 'tbl_users'])
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['data']);
        $this->assertSame('actor', $json['data'][0]['username']);
        $this->assertSame('LOGIN', $json['data'][0]['action']);
        $this->assertSame('actor', $json['data'][0]['target']);
        $this->assertSame('2026-08-15 18:00:00', $json['data'][0]['date_raw']);
        $this->assertSame(['actor'], $json['users']);
        $this->assertSame(['LOGIN'], $json['actions']);
    }

    public function test_audit_data_feed_resolves_client_name(): void
    {
        $this->manager('audit_logs.php');

        $actor = User::factory()->create(['username' => 'actor']);
        $client = $this->client();
        DB::table('tbl_audit_logs')->insert([
            'user_id' => $actor->id,
            'action' => 'ADD_CLIENT',
            'target_table' => 'tbl_clients',
            'target_id' => $client->id,
            'created_at' => '2026-08-15 10:00:00',
        ]);

        $json = $this->postJson(route('admin.audit-logs.data'), ['table' => 'tbl_clients'])
            ->assertOk()
            ->json();

        $this->assertSame('DELA CRUZ, JUAN R', $json['data'][0]['target']);
    }

    public function test_audit_data_feed_resolves_transaction_name(): void
    {
        $this->manager('audit_logs.php');

        $actor = User::factory()->create(['username' => 'actor']);
        $transaction = Transaction::query()->create([
            'client_id' => $this->client()->id,
            'program' => 'AICS',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'remarks' => 'TEST',
            'suggested_amount' => 5000.00,
            'status' => 'PENDING PAYOUT',
        ]);
        DB::table('tbl_audit_logs')->insert([
            'user_id' => $actor->id,
            'action' => 'ADD_TRANSACTION',
            'target_table' => 'tbl_transactions',
            'target_id' => $transaction->id,
            'created_at' => '2026-08-15 10:00:00',
        ]);

        $json = $this->postJson(route('admin.audit-logs.data'), ['table' => 'tbl_transactions'])
            ->assertOk()
            ->json();

        $this->assertSame('DELA CRUZ, JUAN R - AICS', $json['data'][0]['target']);
    }

    public function test_audit_feed_defaults_to_clients_table(): void
    {
        $this->manager('audit_logs.php');

        $json = $this->postJson(route('admin.audit-logs.data'))->assertOk()->json();

        $this->assertIsArray($json['data']);
        $this->assertArrayHasKey('users', $json);
        $this->assertArrayHasKey('actions', $json);
    }

    public function test_leaderboard_returns_descending_totals(): void
    {
        $this->manager('audit_logs.php');

        $actor = User::factory()->create(['username' => 'actor']);
        $viewer = User::query()->where('username', 'admin-audit_logs.php')->firstOrFail();

        foreach (range(1, 2) as $i) {
            DB::table('tbl_audit_logs')->insert([
                'user_id' => $actor->id,
                'action' => 'ADD_CLIENT',
                'target_table' => 'tbl_clients',
                'target_id' => $i,
                'created_at' => '2026-08-15 10:00:00',
            ]);
        }
        DB::table('tbl_audit_logs')->insert([
            'user_id' => $viewer->id,
            'action' => 'ADD_CLIENT',
            'target_table' => 'tbl_clients',
            'target_id' => 3,
            'created_at' => '2026-08-15 10:00:00',
        ]);

        $json = $this->postJson(route('admin.audit-logs.leaderboard'), ['table' => 'tbl_clients'])
            ->assertOk()
            ->json();

        $this->assertCount(2, $json);
        $this->assertSame('actor', $json[0]['username']);
        $this->assertSame(2, $json[0]['total_actions']);
        $this->assertSame($viewer->username, $json[1]['username']);
        $this->assertSame(1, $json[1]['total_actions']);
    }

    public function test_audit_payloads_never_leak_credentials(): void
    {
        $admin = $this->manager('register.php');
        $this->grantPage($admin, 'manage_permissions.php');

        $this->post(route('admin.users.store'), [
            'username' => 'newbie',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $subject = User::factory()->create(['username' => 'subject']);
        $this->post(route('admin.permissions.update-pages', $subject->id), [
            'pages' => ['clients.php'],
            'super_admin' => false,
        ]);

        $payloads = DB::table('tbl_audit_logs')
            ->whereIn('action', ['MANAGE_USER_CREATE', 'MANAGE_PAGE_PERMISSIONS'])
            ->get()
            ->flatMap(fn ($row) => [$row->old_value, $row->new_value])
            ->filter();

        $this->assertGreaterThan(0, $payloads->count());

        foreach ($payloads as $payload) {
            $this->assertStringNotContainsString('password', $payload);
            $this->assertStringNotContainsString('session_token', $payload);
        }
    }
}
