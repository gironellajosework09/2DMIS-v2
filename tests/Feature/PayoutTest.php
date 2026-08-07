<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayoutTest extends TestCase
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
        $user = User::factory()->create(['username' => 'payoutclerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => $page,
            'can_access' => true,
        ]);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['username' => 'root']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => '*',
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
            'type' => 'SCHOLARSHIP',
            'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
            'suggested_amount' => 5000,
            'status' => 'PAID',
        ], $overrides));
    }

    public function test_payout_attendance_screens_are_gated_by_permission(): void
    {
        $this->logInAs(User::factory()->create(['username' => 'clerk']));

        foreach (['scanned_payouts', 'scanned_payouts2', 'scanned_payouts_unpaid'] as $variant) {
            $this->get(route('payout-attendance.'.$variant.'.index'))
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('login_status', 'denied');
        }
    }

    public function test_payout_attendance_screens_load_for_super_admin(): void
    {
        $this->logInAs($this->superAdmin());

        foreach (config('payout.attendance') as $variant => $config) {
            $this->get(route('payout-attendance.'.$variant.'.index'))
                ->assertOk()
                ->assertSee($config['title']);
        }
    }

    public function test_payout_attendance_feed_returns_seats_and_filters(): void
    {
        $client = $this->client();
        $user = $this->pageUser('scanned_payouts2.php');
        $transaction = $this->transaction($client);

        DB::table('tbl_seats2')->insert([
            'program' => 'CEAP',
            'name' => 'DELA CRUZ, JUAN R',
            'town' => 'VIGAN',
            'section' => 'A',
            'box' => '1',
            'row' => '2',
            'seat' => '3',
        ]);

        DB::table('tbl_payout_scans2')->insert([
            'transaction_id' => $transaction->id,
            'scanned_text' => 'DELA CRUZ, JUAN R',
            'scanned_by' => $user->id,
            'scanned_at' => '2026-08-01 04:30:00',
        ]);

        $this->logInAs($user);

        $this->post(route('payout-attendance.scanned_payouts2.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.transaction_id', $transaction->id)
            ->assertJsonPath('data.0.client_name', 'DELA CRUZ, JUAN R')
            ->assertJsonPath('data.0.municipality_name', 'VIGAN')
            ->assertJsonPath('data.0.section', 'A')
            ->assertJsonPath('data.0.box', '1')
            ->assertJsonPath('data.0.row', '2')
            ->assertJsonPath('data.0.seat', '3')
            ->assertJsonPath('data.0.scanned_by_name', 'payoutclerk')
            ->assertJsonPath('data.0.scanned_at', '08/01/2026 - 12:30 PM');

        // Municipality filter excludes the row.
        $this->post(route('payout-attendance.scanned_payouts2.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25, 'municipality' => '99999',
        ])->assertJsonPath('recordsFiltered', 0);

        // Program filter excludes the row.
        $this->post(route('payout-attendance.scanned_payouts2.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25, 'program' => 'TUPAD',
        ])->assertJsonPath('recordsFiltered', 0);

        // Date-range filter matches the scan date.
        $this->post(route('payout-attendance.scanned_payouts2.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25,
            'scanned_start' => '2026-08-01', 'scanned_end' => '2026-08-01',
        ])->assertJsonPath('recordsFiltered', 1);

        // Global search matches the client name.
        $this->post(route('payout-attendance.scanned_payouts2.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25, 'search' => ['value' => 'JUAN'],
        ])->assertJsonPath('recordsFiltered', 1);

        // Single record mode returns the full details with the seat block.
        $scan = DB::table('tbl_payout_scans2')->first();

        $this->post(route('payout-attendance.scanned_payouts2.data'), ['single_id' => $scan->id])
            ->assertOk()
            ->assertJsonPath('single.program', 'CEAP')
            ->assertJsonPath('single.section', 'A')
            ->assertJsonPath('single.scanned_text', 'DELA CRUZ, JUAN R')
            ->assertJsonPath('single.scanned_at', '08/01/2026 - 12:30 PM');
    }

    public function test_payout_attendance_delete_removes_scan_without_audit(): void
    {
        $client = $this->client();
        $user = $this->pageUser('scanned_payouts.php');
        $transaction = $this->transaction($client);

        DB::table('tbl_payout_scans')->insert([
            'transaction_id' => $transaction->id,
            'scanned_text' => 'DELA CRUZ, JUAN R',
            'scanned_by' => $user->id,
        ]);

        $this->logInAs($user);

        $scanId = (int) DB::table('tbl_payout_scans')->first()->id;

        $this->post(route('payout-attendance.scanned_payouts.data'), ['delete_id' => $scanId])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, DB::table('tbl_payout_scans')->count());

        // v1 wrote no audit row for the attendance deletes — parity preserved.
        $this->assertSame(0, DB::table('tbl_audit_logs')->count());
    }

    public function test_payout_attendance_unpaid_feed_uses_patient_name_and_no_seats(): void
    {
        $client = $this->client();
        $user = $this->pageUser('scanned_payouts_unpaid.php');
        $transaction = $this->transaction($client, [
            'patient_name' => 'SANTOS, MARIA C',
            'status' => 'PENDING PAYOUT',
        ]);

        DB::table('tbl_payout_scans_unpaid')->insert([
            'transaction_id' => $transaction->id,
            'scanned_text' => 'SANTOS, MARIA C',
            'scanned_by' => $user->id,
            'scanned_at' => '2026-08-02 04:30:00',
        ]);

        $this->logInAs($user);

        $this->post(route('payout-attendance.scanned_payouts_unpaid.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25,
        ])
            ->assertOk()
            ->assertJsonPath('data.0.client_name', 'SANTOS, MARIA C')
            ->assertJsonPath('data.0.scanned_text', 'SANTOS, MARIA C')
            ->assertJsonMissingPath('data.0.section');

        // The unpaid screen's program filter list is the four-program set.
        $this->get(route('payout-attendance.scanned_payouts_unpaid.index'))
            ->assertOk()
            ->assertSee('OTCES');
    }

    public function test_unpaid_verification_admin_screen_is_gated(): void
    {
        $this->logInAs(User::factory()->create(['username' => 'clerk']));

        $this->get(route('unpaid-verifications.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    public function test_unpaid_verification_self_service_is_public(): void
    {
        $this->get(route('unpaid-verification.self-service'))
            ->assertOk()
            ->assertSee('Unpaid Verification');
    }

    public function test_unpaid_verification_search_lists_municipalities(): void
    {
        Municipality::query()->create(['name' => 'VIGAN', 'code' => 'VIG']);

        $this->get(route('grantee-search', ['kind' => 'unpaid']).'?munis=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('municipalities.0.name', 'VIGAN');
    }

    public function test_unpaid_verification_search_only_matches_pending_unpaid_programs(): void
    {
        $pending = $this->client([
            'lastname' => 'REYES', 'firstname' => 'PEDRO', 'middlename' => 'D',
            'full_name' => 'REYES, PEDRO D', 'match_name' => 'REYESPEDROD',
        ]);
        $this->transaction($pending, ['program' => 'OTCES', 'status' => 'PENDING PAYOUT']);

        $paid = $this->client([
            'lastname' => 'SANTOS', 'firstname' => 'MARIA', 'middlename' => 'C',
            'full_name' => 'SANTOS, MARIA C', 'match_name' => 'SANTOSMARIAC',
        ]);
        $this->transaction($paid, ['program' => 'CEAP', 'status' => 'PAID']);

        $this->get(route('grantee-search', ['kind' => 'unpaid']).'?q=REYES')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('results.0.id', $pending->id)
            ->assertJsonCount(1, 'results');
    }

    public function test_unpaid_verification_verify_checks_municipality_and_program(): void
    {
        $client = $this->client();
        $this->transaction($client, ['status' => 'PENDING PAYOUT']);

        // Wrong municipality is rejected.
        $this->post(route('grantee-search.verify', ['kind' => 'unpaid']), [
            'action' => 'verify',
            'client_id' => $client->id,
            'municipality_id' => '99999',
        ])->assertOk()
            ->assertJson(['success' => false, 'message' => 'Municipality does not match our records.']);

        // Matching municipality returns the client + latest qualifying program.
        $this->post(route('grantee-search.verify', ['kind' => 'unpaid']), [
            'action' => 'verify',
            'client_id' => $client->id,
            'municipality_id' => $client->city_municipality,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('program', 'CEAP')
            ->assertJsonPath('client.id', $client->id)
            ->assertJsonPath('client.lastname', 'DELA CRUZ');

        // Missing parameters and unknown clients are rejected.
        $this->post(route('grantee-search.verify', ['kind' => 'unpaid']), [
            'action' => 'verify', 'client_id' => 0, 'municipality_id' => 0,
        ])->assertJson(['success' => false, 'message' => 'Missing required parameters']);

        $this->post(route('grantee-search.verify', ['kind' => 'unpaid']), [
            'action' => 'verify', 'client_id' => 999999, 'municipality_id' => 1,
        ])->assertJson(['success' => false, 'message' => 'Client not found']);
    }

    public function test_unpaid_verification_store_creates_row_without_audit(): void
    {
        $client = $this->client();
        $this->transaction($client, ['status' => 'PENDING PAYOUT']);

        $this->post(route('unpaid-verification.submit'), [
            'client_id' => $client->id,
            'municipality_id' => $client->city_municipality,
            'is_proxy' => '1',
            'proxy_lastname' => ' del rosario ',
            'proxy_firstname' => 'Ana',
            'proxy_middlename' => 'M',
            'proxy_relationship' => 'Child',
            'proxy_phone' => '0917',
            'proxy_birthdate' => '2000-01-01',
            'proxy_gender' => 'Female',
            'proxy_occupation' => 'Student',
            'proxy_monthlyincome' => '0',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'PROXY INFORMATION FOR DEL ROSARIO, ANA M RECORDED SUCCESSFULLY. YOU MAY NOW CLOSE THIS WINDOW.');

        $row = DB::table('tbl_unpaid_verifications')->firstOrFail();

        $this->assertSame($client->id, (int) $row->client_id);
        $this->assertSame(1, (int) $row->is_proxy);
        $this->assertSame('DEL ROSARIO', $row->proxy_lastname);
        $this->assertSame('ANA', $row->proxy_firstname);
        $this->assertSame('M', $row->proxy_middlename);
        $this->assertNotNull($row->created_at);

        // v1 unpaid_save.php wrote no audit row — parity preserved.
        $this->assertSame(0, DB::table('tbl_audit_logs')->count());
    }

    public function test_unpaid_verification_store_blocks_duplicate_submission(): void
    {
        $client = $this->client();
        $this->transaction($client, ['status' => 'PENDING PAYOUT']);

        $payload = [
            'client_id' => $client->id,
            'municipality_id' => $client->city_municipality,
            'is_proxy' => '0',
        ];

        $this->post(route('unpaid-verification.submit'), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'ATTENDANCE CONFIRMED SUCCESSFULLY. YOU MAY NOW CLOSE THIS WINDOW.');

        $this->post(route('unpaid-verification.submit'), $payload)
            ->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'You have already submitted your confirmation. Multiple submissions are not allowed.',
            ]);

        $this->assertSame(1, DB::table('tbl_unpaid_verifications')->count());
    }

    public function test_unpaid_verification_store_requires_client_and_municipality(): void
    {
        $this->post(route('unpaid-verification.submit'), [])
            ->assertOk()
            ->assertJson(['success' => false, 'message' => 'Missing client or municipality.']);
    }

    public function test_unpaid_verification_feed_and_filters(): void
    {
        $client = $this->client();
        $this->transaction($client, ['status' => 'PENDING PAYOUT']);

        DB::table('tbl_unpaid_verifications')->insert([
            'client_id' => $client->id,
            'municipality_id' => $client->city_municipality,
            'is_proxy' => 1,
            'proxy_lastname' => 'DEL ROSARIO',
            'proxy_firstname' => 'ANA',
            'proxy_middlename' => 'M',
            'proxy_relationship' => 'CHILD',
            'proxy_phone' => '0917',
            'created_at' => now(),
        ]);

        $user = $this->pageUser('unpaid_verifications.php');
        $this->logInAs($user);

        $this->post(route('unpaid-verifications.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25,
        ])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.client_name', 'DELA CRUZ, JUAN R')
            ->assertJsonPath('data.0.is_proxy_label', 'YES')
            ->assertJsonPath('data.0.proxy_fullname', 'DEL ROSARIO, ANA M')
            ->assertJsonPath('data.0.proxy_relationship', 'CHILD');

        // Municipality filter.
        $this->post(route('unpaid-verifications.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25, 'municipality' => '99999',
        ])->assertJsonPath('recordsFiltered', 0);

        // Search hits the proxy phone column.
        $this->post(route('unpaid-verifications.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25, 'search' => ['value' => '0917'],
        ])->assertJsonPath('recordsFiltered', 1);

        // Single record mode.
        $id = (int) DB::table('tbl_unpaid_verifications')->first()->id;

        $this->post(route('unpaid-verifications.data'), ['single_id' => $id])
            ->assertOk()
            ->assertJsonPath('single.client_name', 'DELA CRUZ, JUAN R')
            ->assertJsonPath('single.proxy_fullname', 'DEL ROSARIO, ANA M');

        // Delete removes the row (no audit, v1 parity).
        $this->post(route('unpaid-verifications.data'), ['delete_id' => $id])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, DB::table('tbl_unpaid_verifications')->count());
        $this->assertSame(0, DB::table('tbl_audit_logs')->count());
    }

    public function test_unpaid_verification_export_has_bom_and_v1_columns(): void
    {
        $client = $this->client();
        $this->transaction($client, ['status' => 'PENDING PAYOUT']);

        DB::table('tbl_unpaid_verifications')->insert([
            'client_id' => $client->id,
            'municipality_id' => $client->city_municipality,
            'is_proxy' => 0,
            'created_at' => now(),
        ]);

        $user = $this->pageUser('unpaid_verifications.php');
        $this->logInAs($user);

        $response = $this->get(route('unpaid-verifications.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Strip the BOM, then parse the header line exactly as fputcsv wrote it.
        $header = str_getcsv(substr(strtok($content, "\n"), 3));
        $this->assertSame([
            'ID', 'Client Name', 'Municipality', 'Is Proxy?', 'Proxy Name', 'Relationship',
            'Phone', 'Birthdate', 'Gender', 'Occupation', 'Monthly Income', 'Submitted At',
        ], $header);
        $this->assertStringContainsString('DELA CRUZ, JUAN R', $content);
    }
}
