<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * C3-D — token-first client resolution within the P4 scanner engine.
 *
 * Contract under test:
 *   scanned value -> exact qr_token lookup (utf8mb4_bin, UNIQUE index)
 *                 -> legacy TRIM(full_name) utf8mb4_general_ci fallback
 *                 -> existing specialized strategy behavior
 *
 * The token path is additive and only reaches client-identity strategies
 * (client / client_geo / existing_program / exam_derived). Transaction,
 * partial-transaction and seat-join strategies keep their own payload types
 * and never route through the token lookup.
 */
class ScannerTokenResolutionTest extends TestCase
{
    use RefreshDatabase;

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

    private function scannerUser(string $page): User
    {
        $user = User::factory()->create(['username' => 'scanclerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => $page,
            'can_access' => true,
        ]);

        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);

        return $user;
    }

    public function test_qr_token_resolves_the_exact_client(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_ceap.php');

        $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => $client->qr_token])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.full_name', 'DELA CRUZ, JUAN R');

        $this->assertSame($client->id, app(ScanService::class)->resolveClient($client->qr_token)?->id);
    }

    public function test_unknown_token_falls_back_to_legacy_full_name(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_ceap.php');

        // A 16-char base62 string that is neither a stored token nor a name.
        $ghost = 'Zz9Xq2KvYw8Aa1Bc';
        $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => $ghost])
            ->assertJson(['success' => false]);

        // A full_name whose literal text looks like a token: the token lookup
        // misses, then the legacy name fallback finds the client.
        $named = $this->client([
            'lastname' => 'ABCDEFGHIJKLMNOP',
            'firstname' => 'X',
            'full_name' => 'ABCDEFGHIJKLMNOP',
            'match_name' => 'ABCDEFGHIJKLMNOP',
        ]);

        $this->assertNotSame($named->qr_token, 'ABCDEFGHIJKLMNOP');
        $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => 'ABCDEFGHIJKLMNOP'])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $named->id);
    }

    public function test_legacy_full_name_still_resolves(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_ceap.php');

        $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $client->id);
    }

    public function test_token_lookup_is_exact_and_never_partial(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_ceap.php');

        // A prefix / suffix of the stored token must NOT resolve.
        $prefix = substr($client->qr_token, 0, 15);
        $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => $prefix])
            ->assertJson(['success' => false]);

        $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => $client->qr_token.'X'])
            ->assertJson(['success' => false]);
    }

    public function test_token_lookup_is_case_sensitive(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_ceap.php');

        $mutated = strtoupper($client->qr_token);

        $this->assertNotSame($client->qr_token, $mutated);

        // The case-mutated value is not the stored token (utf8mb4_bin): it must
        // not resolve. It is also not a stored name, so the legacy fallback
        // misses -> the scan fails. It may NEVER resolve to the client.
        $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => $mutated])
            ->assertJson(['success' => false]);
    }

    public function test_token_resolves_to_exactly_one_client(): void
    {
        $a = $this->client(['lastname' => 'AAA']);
        $this->client(['lastname' => 'BBB']);

        $matches = DB::table('tbl_clients')->where('qr_token', $a->qr_token)->count();
        $this->assertSame(1, $matches);
    }

    public function test_name_edits_do_not_affect_token_resolution(): void
    {
        $client = $this->client();
        $token = $client->qr_token;

        $client->fill(['lastname' => 'REYES', 'full_name' => 'REYES, JUAN R'])->save();

        $this->assertSame($client->id, app(ScanService::class)->resolveClient($token)?->id);
        $this->assertSame('REYES, JUAN R', app(ScanService::class)->resolveClient($token)?->full_name);
    }

    public function test_extension_client_resolves_by_token_independent_of_display_formatter(): void
    {
        $client = $this->client([
            'lastname' => 'TESTCLIENT 0014',
            'firstname' => 'MARIA',
            'middlename' => 'L',
            'extensionname' => 'JR',
            'full_name' => 'TESTCLIENT 0014, MARIA L JR',
            'match_name' => 'TESTCLIENT0014MARIALJR',
        ]);

        $this->scannerUser('scanner_ceap.php');

        // Persisted v1 order is "LAST, FIRST MIDDLE EXT"; token resolution must
        // return it untouched — no name reconstruction, no display reordering.
        $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => $client->qr_token])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.full_name', 'TESTCLIENT 0014, MARIA L JR');

        // The canonical (C3-B) display formatter is independent of resolution:
        // it shows "LAST, FIRST (EXT) MIDDLE" while resolution keeps full_name.
        $this->assertSame('TESTCLIENT 0014, MARIA (JR) L', $client->displayFullName());
    }

    public function test_gironella_duplicate_name_pair_resolves_independently_by_token(): void
    {
        $joseMiddle = $this->client([
            'lastname' => 'GIRONELLA',
            'firstname' => 'JOSE',
            'middlename' => 'SOLIVIO',
            'extensionname' => null,
            'full_name' => 'GIRONELLA, JOSE SOLIVIO',
            'match_name' => 'GIRONELLAJOSESOLIVIO',
        ]);
        $josePlain = $this->client([
            'lastname' => 'GIRONELLA',
            'firstname' => 'JOSE',
            'middlename' => null,
            'extensionname' => null,
            'full_name' => 'GIRONELLA, JOSE',
            'match_name' => 'GIRONELLAJO',
        ]);

        $this->assertNotSame($joseMiddle->qr_token, $josePlain->qr_token);

        app(ScanService::class);
        $this->assertSame($joseMiddle->id, app(ScanService::class)->resolveClient($joseMiddle->qr_token)?->id);
        $this->assertSame($josePlain->id, app(ScanService::class)->resolveClient($josePlain->qr_token)?->id);

        // Legacy name lookup keeps its existing (first-row) behavior — unchanged.
        $this->scannerUser('scanner_ceap.php');
        $response = $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => 'GIRONELLA, JOSE']);
        $response->assertJson(['success' => true]);
        $this->assertContains($response->json('data.id'), [$joseMiddle->id, $josePlain->id]);
    }

    public function test_two_clients_with_distinct_tokens_resolve_distinct_clients(): void
    {
        $a = $this->client(['lastname' => 'AAA', 'full_name' => 'AAA, ONE']);
        $b = $this->client(['lastname' => 'BBB', 'full_name' => 'BBB, TWO']);

        $this->assertSame($a->id, app(ScanService::class)->resolveClient($a->qr_token)?->id);
        $this->assertSame($b->id, app(ScanService::class)->resolveClient($b->qr_token)?->id);
    }

    public function test_client_geo_strategy_resolves_by_token(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_toda.php');

        $this->post(route('scanners.toda.lookup'), ['action' => 'lookup', 'scanned' => $client->qr_token])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.municipality', 'VIGAN')
            ->assertJsonPath('data.barangay', 'BARANGAY I');
    }

    public function test_existing_program_strategy_resolves_by_token(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_ongoing_scholars.php');

        Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'CEAP',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-01-10',
            'type' => 'SCHOLARSHIP',
            'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
            'suggested_amount' => 5000,
            'status' => 'PENDING PAYOUT',
        ]);

        $this->post(route('scanners.ongoing_scholars.lookup'), ['action' => 'lookup', 'scanned' => $client->qr_token])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.program', 'CEAP');
    }

    public function test_transaction_strategy_stays_transaction_based_and_rejects_tokens(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_cedssg_update.php');

        $transaction = Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'CEDSSG',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-01-10',
            'type' => 'SCHOLARSHIP',
            'remarks' => '2ND SEM SY 2025-2026 DOCS SUBMITTED',
            'suggested_amount' => 11600,
            'status' => 'PENDING PAYOUT',
        ]);

        // Legacy patient_name input continues to resolve the transaction.
        $this->post(route('scanners.cedssg_update.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.transaction_id', $transaction->id);

        // The client token is a different payload type for this strategy: it is
        // matched against patient_name only and must NOT resolve.
        $this->post(route('scanners.cedssg_update.lookup'), ['action' => 'lookup', 'scanned' => $client->qr_token])
            ->assertJson(['success' => false]);
    }

    public function test_seat_join_strategy_stays_seat_based_and_rejects_tokens(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_payout.php');

        $transaction = Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'CEAP',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-01-10',
            'type' => 'SCHOLARSHIP',
            'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
            'suggested_amount' => 5000,
            'status' => 'PAID',
            'amount_paid' => 5000,
        ]);

        DB::table('tbl_seats2')->insert([
            'program' => 'CEAP',
            'name' => 'DELA CRUZ, JUAN R',
            'town' => 'VIGAN',
            'section' => 'A',
            'box' => '1',
            'row' => '2',
            'seat' => '3',
        ]);

        // Legacy seat-name input continues to resolve through the seat join.
        $this->post(route('scanners.payout.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $transaction->id);

        // The token is not a seat name: it must not inject into the seat join.
        $this->post(route('scanners.payout.lookup'), ['action' => 'lookup', 'scanned' => $client->qr_token])
            ->assertJson(['success' => false]);
    }

    public function test_exam_derived_strategy_keeps_legacy_behavior_and_resolves_by_token(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_new_scholars.php');

        DB::table('tbl_exam')->insert([
            'exam_no' => 'EX-001',
            'fullname' => 'DELA CRUZ, JUAN R',
            'barangay' => 'BARANGAY I',
            'town' => 'VIGAN',
            'email_address' => 'juan@test.ph',
            'contact' => '0917',
            'school' => 'UNIV',
            'course' => 'BSIT',
            'year' => 1,
            'scholarship' => 'CEAP',
            'exam_date' => '2026-07-01',
            'exam_time' => '08:00',
            'permit_confirmed' => 1,
            'score' => '90',
        ]);
        DB::table('tbl_results')->insert([
            'exam_no' => 'EX-001',
            'score' => '90',
            'approved' => 'CEAP_NEW',
        ]);

        // Legacy: the scanned name is the exam key — unchanged.
        $this->post(route('scanners.new_scholars.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.program', 'CEAP_NEW');

        // Token: client identity resolves first, then the unchanged name-keyed
        // exam/result linkage is reached through the client's persisted name.
        $this->post(route('scanners.new_scholars.lookup'), ['action' => 'lookup', 'scanned' => $client->qr_token])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.program', 'CEAP_NEW');
    }

    public function test_lookup_output_shape_is_unchanged_for_client_strategies(): void
    {
        $client = $this->client();
        $this->scannerUser('scanner_ceap.php');

        $byToken = $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => $client->qr_token])->json();
        $byName = $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])->json();

        $this->assertSame(['id', 'full_name'], array_keys($byToken['data']));
        $this->assertSame($byName['data'], $byToken['data']);
    }

    public function test_resolver_uses_the_qr_token_unique_index(): void
    {
        $client = $this->client();

        // Existing token: const-type lookup through the unique index.
        $existing = (array) DB::selectOne('EXPLAIN SELECT * FROM tbl_clients WHERE qr_token = ?', [$client->qr_token]);
        $this->assertSame('tbl_clients_qr_token_unique', $existing['key']);
        $this->assertSame('const', $existing['type']);

        // Nonexistent token: the unique index marks the const impossible.
        // MariaDB folds the lookup — "Impossible WHERE noticed after reading
        // const tables" — proof that the planner reasoned over the unique index
        // and skipped the table entirely rather than scanning.
        $missing = (array) DB::selectOne('EXPLAIN SELECT * FROM tbl_clients WHERE qr_token = ?', ['__TOKEN_NOOP__']);
        $this->assertStringContainsString('Impossible WHERE', (string) $missing['Extra']);
    }
}
