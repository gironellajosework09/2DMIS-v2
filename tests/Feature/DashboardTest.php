<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\ProgramPermission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function place(string $name = 'VIGAN'): array
    {
        $municipality = Municipality::query()->create(['name' => $name, 'code' => strtoupper(substr($name, 0, 3))]);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'BARANGAY I',
        ]);

        return [$municipality, $barangay];
    }

    private function client(Municipality $municipality, Barangay $barangay): Client
    {
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

    private function transaction(Client $client, string $program = 'AICS', string $status = 'PAID', float $amount = 5000): Transaction
    {
        return Transaction::query()->create([
            'client_id' => $client->id,
            'program' => $program,
            'patient_name' => $client->full_name,
            'date_applied' => now()->toDateString(),
            'type' => 'OCA',
            'remarks' => 'TEST',
            'suggested_amount' => $amount,
            'status' => $status,
            'amount_paid' => $status === 'PAID' ? $amount : 0,
        ]);
    }

    private function auditLog(int $userId, string $action = 'ADD_CLIENT', string $table = 'tbl_clients', int $targetId = 1): void
    {
        DB::table('tbl_audit_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'target_table' => $table,
            'target_id' => $targetId,
            'created_at' => now(),
        ]);
    }

    // ── KPI card assertions ─────────────────────────────────────────

    public function test_dashboard_renders_kpi_cards(): void
    {
        [$municipality, $barangay] = $this->place();
        $client = $this->client($municipality, $barangay);
        $this->transaction($client, 'AICS', 'PAID', 5000);
        $this->transaction($client, 'AICS', 'PENDING PAYOUT', 3000);

        $user = User::factory()->create();
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'clients.php', 'can_access' => true]);
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'all_transactions.php', 'can_access' => true]);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $response->assertSee('Total Registered Clients');
        $response->assertSee('Assistance Transactions');
        $response->assertSee('5,000');
        $response->assertSee('Pending Approvals');
    }

    public function test_dashboard_kpi_zero_state(): void
    {
        $user = User::factory()->create();
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'clients.php', 'can_access' => true]);
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'all_transactions.php', 'can_access' => true]);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('₱0.00');
    }

    // ── Program distribution ─────────────────────────────────────────

    public function test_program_distribution_groups_by_program(): void
    {
        [$municipality, $barangay] = $this->place();
        $client = $this->client($municipality, $barangay);
        $this->transaction($client, 'AICS', 'PAID');
        $this->transaction($client, 'AICS', 'PAID');
        $this->transaction($client, 'TUPAD', 'PAID');

        $user = User::factory()->create();
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'clients.php', 'can_access' => true]);
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'all_transactions.php', 'can_access' => true]);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Program distribution');
        $response->assertSee('AICS');
        $response->assertSee('TUPAD');
        $response->assertSee('2 transactions');
        $response->assertSee('1 transactions');
    }

    public function test_program_distribution_empty_when_no_transactions(): void
    {
        $user = User::factory()->create();
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'clients.php', 'can_access' => true]);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('No transactions recorded yet');
    }

    // ── Activity feed ────────────────────────────────────────────────

    public function test_activity_feed_shows_for_permitted_user(): void
    {
        $user = User::factory()->create(['username' => 'clerk']);
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'audit_logs.php', 'can_access' => true]);
        $this->auditLog($user->id, 'ADD_CLIENT', 'tbl_clients', 42);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Recent activity');
        $response->assertSee('clerk');
        $response->assertSee('added a client');
        $response->assertSee('clients #42');
    }

    public function test_activity_feed_hidden_for_non_permitted_user(): void
    {
        $user = User::factory()->create(['username' => 'clerk']);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertDontSee('Recent activity');
    }

    // ── Quick actions ────────────────────────────────────────────────

    public function test_quick_actions_shows_when_page_permitted(): void
    {
        $user = User::factory()->create();
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'clients.php', 'can_access' => true]);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Add Client');
    }

    public function test_quick_actions_hidden_when_no_permission(): void
    {
        $user = User::factory()->create();
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertDontSee('Add Client');
    }

    // ── Recent transactions widget ───────────────────────────────────

    public function test_recent_transactions_widget_loads_for_permitted_user(): void
    {
        $user = User::factory()->create();
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'all_transactions.php', 'can_access' => true]);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Recent transactions');
        $response->assertSee('View all');
        $response->assertSee('recent-transactions-body');
    }

    public function test_recent_transactions_widget_hidden_for_non_permitted_user(): void
    {
        $user = User::factory()->create();
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertDontSee('Recent transactions');
    }

    // ── Municipality scope enforcement ───────────────────────────────

    public function test_kpi_respects_municipality_scope(): void
    {
        [$muni1, $bgy1] = $this->place('VIGAN');
        [$muni2, $bgy2] = $this->place('CANDON');

        $client1 = $this->client($muni1, $bgy1);
        $client2 = $this->client($muni2, $bgy2);
        $this->transaction($client1, 'AICS', 'PAID', 5000);
        $this->transaction($client2, 'AICS', 'PAID', 10000);

        $user = User::factory()->create();
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'clients.php', 'can_access' => true]);
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'all_transactions.php', 'can_access' => true]);
        DB::table('tbl_user_municipalities')->insert([
            'user_id' => $user->id,
            'municipality_id' => $muni1->id,
        ]);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        // Should see VIGAN client (1) but not CANDON (1) → total = 1
        $response->assertSee('1');
        // Should see VIGAN transaction amount (5,000) but not CANDON (10,000)
        $response->assertSee('5,000');
        $response->assertDontSee('10,000');
    }

    public function test_program_permissions_filter_kpi(): void
    {
        [$municipality, $barangay] = $this->place();
        $client = $this->client($municipality, $barangay);
        $this->transaction($client, 'AICS', 'PAID', 5000);
        $this->transaction($client, 'TUPAD', 'PAID', 3000);

        $user = User::factory()->create();
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'clients.php', 'can_access' => true]);
        Permission::query()->create(['user_id' => $user->id, 'page_name' => 'all_transactions.php', 'can_access' => true]);
        ProgramPermission::query()->create([
            'user_id' => $user->id,
            'program_name' => 'AICS',
        ]);
        $this->logInAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        // Only AICS visible → disbursed = 5000
        $response->assertSee('5,000');
        $response->assertDontSee('3,000');
    }

    // ── Unauthenticated access ───────────────────────────────────────

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}
