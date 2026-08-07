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

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function scannerUser(string $page): User
    {
        $user = User::factory()->create(['username' => 'scanclerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => $page,
            'can_access' => true,
        ]);

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

    public function test_scanner_page_is_gated_by_permission(): void
    {
        $this->logInAs(User::factory()->create(['username' => 'clerk']));

        $this->get(route('scanners.ceap'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    public function test_all_scanner_pages_load_for_super_admin(): void
    {
        $user = User::factory()->create(['username' => 'root']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => '*',
            'can_access' => true,
        ]);

        $this->logInAs($user);

        foreach (array_keys(config('scanner.scanners')) as $key) {
            $this->get(route('scanners.'.$key))->assertOk();
        }
    }

    public function test_ceap_lookup_and_save_creates_transaction_and_audit(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_ceap.php');

        $this->logInAs($user);

        $this->post(route('scanners.ceap.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJson(['success' => true, 'data' => ['id' => $client->id]]);

        $this->post(route('scanners.ceap.save'), ['action' => 'save', 'id' => $client->id])
            ->assertJson(['success' => true]);

        $transaction = Transaction::query()->firstOrFail();

        $this->assertSame('CEAP', $transaction->program);
        $this->assertSame('DELA CRUZ, JUAN R', $transaction->patient_name);
        $this->assertSame('SCHOLARSHIP', $transaction->type);
        $this->assertSame('1ST SEM SY2025-2026 DOCS SUBMITTED', $transaction->remarks);
        $this->assertSame(5000.0, (float) $transaction->suggested_amount);
        $this->assertSame('PENDING PAYOUT', $transaction->status);

        $audit = DB::table('tbl_audit_logs')->where('action', 'SCAN-CEAP')->first();
        $this->assertNotNull($audit);
        $this->assertSame($transaction->id, (int) $audit->target_id);
        $this->assertStringContainsString('CEAP', (string) $audit->new_value);
    }

    public function test_ceap_duplicate_scan_is_blocked(): void
    {
        $client = $this->client();
        $this->logInAs($this->scannerUser('scanner_ceap.php'));

        $this->post(route('scanners.ceap.save'), ['action' => 'save', 'id' => $client->id])->assertJson(['success' => true]);
        $this->post(route('scanners.ceap.save'), ['action' => 'save', 'id' => $client->id])
            ->assertJson(['success' => false]);

        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_otea_and_otces_use_their_semester_templates(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_otea.php');

        $this->logInAs($user);

        $this->post(route('scanners.otea.save'), ['action' => 'save', 'id' => $client->id])->assertJson(['success' => true]);

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('OTEA', $transaction->program);
        $this->assertSame('SCHOOL YEAR 2025-2026', $transaction->remarks);
        $this->assertSame(5000.0, (float) $transaction->suggested_amount);
        $this->assertSame('2025-08-18', $transaction->payout_date);
    }

    public function test_toda_lookup_returns_geo_and_save_is_date_guarded(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_toda.php');

        $this->logInAs($user);

        $this->post(route('scanners.toda.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJsonPath('data.municipality', 'VIGAN')
            ->assertJsonPath('data.barangay', 'BARANGAY I');

        $this->post(route('scanners.toda.save'), [
            'action' => 'save',
            'id' => $client->id,
            'date_applied' => '2026-08-01',
            'date_paid' => '2026-08-01',
            'amount_paid' => '2500',
        ])->assertJson(['success' => true]);

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('TODA', $transaction->program);
        $this->assertSame('CASH RELIEF ASSISTANCE', $transaction->type);
        $this->assertSame('PAID', $transaction->status);
        $this->assertSame(2500.0, (float) $transaction->amount_paid);
        $this->assertSame('2026-08-01', $transaction->date_applied);

        $audit = DB::table('tbl_audit_logs')->where('action', 'SCAN-TODA')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('2500', (string) $audit->new_value);

        // Same client + same date applied is a duplicate.
        $this->post(route('scanners.toda.save'), [
            'action' => 'save',
            'id' => $client->id,
            'date_applied' => '2026-08-01',
            'date_paid' => '2026-08-01',
            'amount_paid' => '2500',
        ])->assertJson(['success' => false, 'alreadySaved' => true]);

        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_tupad_save_uses_stored_and_audit_remarks_and_existing_details(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_tupad.php');

        $this->logInAs($user);

        $this->post(route('scanners.tupad.save'), [
            'action' => 'save',
            'id' => $client->id,
            'date_applied' => '2026-08-01',
            'date_paid' => '2026-08-01',
        ])->assertJson(['success' => true]);

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('TUPAD', $transaction->program);
        $this->assertSame('CASH FOR WORK', $transaction->type);
        $this->assertSame('TUPAD LGBTQIA+', $transaction->remarks);
        $this->assertSame(4680.0, (float) $transaction->amount_paid);

        $audit = DB::table('tbl_audit_logs')->where('action', 'SCAN-TUPAD')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('TUPAD REGISTRATION 2025', (string) $audit->new_value);
        $this->assertStringNotContainsString('TUPAD LGBTQIA+', (string) $audit->new_value);

        $response = $this->post(route('scanners.tupad.save'), [
            'action' => 'save',
            'id' => $client->id,
            'date_applied' => '2026-08-01',
            'date_paid' => '2026-08-01',
        ]);

        $response->assertJson(['success' => false, 'alreadySaved' => true]);
        $this->assertSame('TUPAD transaction already recorded for this client on 2026-08-01.', $response->json('message'));
        $this->assertSame($transaction->id, (int) $response->json('existing.id'));

        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_cedssg_update_marks_pending_second_sem_as_paid(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_cedssg_update.php');

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

        $this->logInAs($user);

        $this->post(route('scanners.cedssg_update.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJsonPath('data.transaction_id', $transaction->id)
            ->assertJsonPath('data.status', 'PENDING PAYOUT');

        $this->post(route('scanners.cedssg_update.save'), [
            'action' => 'save',
            'transaction_id' => $transaction->id,
            'date_paid' => '2026-08-01',
        ])->assertJson(['success' => true]);

        $transaction->refresh();
        $this->assertSame('PAID', $transaction->status);
        $this->assertSame(12500.0, (float) $transaction->amount_paid);
        $this->assertSame('2026-08-01', $transaction->date_paid);

        $audit = DB::table('tbl_audit_logs')->where('action', 'UPDATE-CEDSSG-PAYMENT')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('"amount_paid":12500', (string) $audit->new_value);

        // Idempotent — repeating the update does not error.
        $this->post(route('scanners.cedssg_update.save'), [
            'action' => 'save',
            'transaction_id' => $transaction->id,
            'date_paid' => '2026-08-02',
        ])->assertJson(['success' => true]);
    }

    public function test_new_scholars_derives_program_from_exam_results(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_new_scholars.php');

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

        $this->logInAs($user);

        $this->post(route('scanners.new_scholars.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJsonPath('data.program', 'CEAP_NEW');

        $this->post(route('scanners.new_scholars.save'), ['action' => 'save', 'id' => $client->id])
            ->assertJson(['success' => true]);

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('CEAP_NEW', $transaction->program);
        $this->assertSame('1ST SEM SY2025-2026 DOCS SUBMITTED', $transaction->remarks);
        $this->assertSame(5000.0, (float) $transaction->suggested_amount);
        $this->assertSame('2025-08-18', $transaction->payout_date);

        $audit = DB::table('tbl_audit_logs')->where('action', 'SCAN-CEAP_NEW')->first();
        $this->assertNotNull($audit);

        // A second save is a remark-key duplicate.
        $this->post(route('scanners.new_scholars.save'), ['action' => 'save', 'id' => $client->id])
            ->assertJson(['success' => false]);
        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_ongoing_scholars_uses_latest_program_and_no_audit(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_ongoing_scholars.php');

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

        $this->logInAs($user);

        $this->post(route('scanners.ongoing_scholars.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJsonPath('data.program', 'CEAP');

        $this->post(route('scanners.ongoing_scholars.save'), ['action' => 'save', 'id' => $client->id])
            ->assertJson(['success' => true]);

        $transaction = Transaction::query()
            ->where('program', 'CEAP')
            ->orderByDesc('id')
            ->first();
        $this->assertSame('2ND SEM SY 2025-2026 DOCS SUBMITTED', $transaction->remarks);
        $this->assertSame(5000.0, (float) $transaction->suggested_amount);

        $this->assertSame(0, DB::table('tbl_audit_logs')->count());

        $this->post(route('scanners.ongoing_scholars.save'), ['action' => 'save', 'id' => $client->id])
            ->assertJson(['success' => false]);
        $this->assertSame(2, Transaction::query()->count());
    }

    public function test_payout_seat_attendance_records_one_scan_per_transaction(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_payout.php');

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

        $this->logInAs($user);

        $this->post(route('scanners.payout.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.section', 'A');

        $this->post(route('scanners.payout.save'), [
            'action' => 'save',
            'id' => $transaction->id,
            'scanned' => 'DELA CRUZ, JUAN R',
        ])->assertJson(['success' => true]);

        $scan = DB::table('tbl_payout_scans2')->first();
        $this->assertSame($transaction->id, (int) $scan->transaction_id);
        $this->assertSame($user->id, (int) $scan->scanned_by);
        $this->assertSame('DELA CRUZ, JUAN R', $scan->scanned_text);

        // Second lookup reports already scanned; ignore-scan still returns details.
        $this->post(route('scanners.payout.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJson(['success' => false]);

        $this->post(route('scanners.payout.lookup'), ['action' => 'lookup_ignore_scan', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJsonPath('data.id', $transaction->id);

        // DB UNIQUE guard blocks the second insert.
        $this->post(route('scanners.payout.save'), [
            'action' => 'save',
            'id' => $transaction->id,
            'scanned' => 'DELA CRUZ, JUAN R',
        ])->assertJson(['success' => false]);

        $this->assertSame(1, DB::table('tbl_payout_scans2')->count());
    }

    public function test_payout_unpaid_partial_match_and_scan(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_payout_unpaid.php');

        $transaction = Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'CEAP',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-01-10',
            'type' => 'SCHOLARSHIP',
            'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
            'suggested_amount' => 5000,
            'status' => 'PENDING PAYOUT',
        ]);

        $this->logInAs($user);

        $this->post(route('scanners.payout_unpaid.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ'])
            ->assertJsonPath('data.id', $transaction->id);

        $this->post(route('scanners.payout_unpaid.save'), [
            'action' => 'save',
            'id' => $transaction->id,
            'scanned' => 'DELA CRUZ',
        ])->assertJson(['success' => true]);

        $scan = DB::table('tbl_payout_scans_unpaid')->first();
        $this->assertSame($transaction->id, (int) $scan->transaction_id);
        $this->assertSame($user->id, (int) $scan->scanned_by);

        $this->post(route('scanners.payout_unpaid.save'), [
            'action' => 'save',
            'id' => $transaction->id,
            'scanned' => 'DELA CRUZ',
        ])->assertJson(['success' => false]);

        $this->assertSame(1, DB::table('tbl_payout_scans_unpaid')->count());
    }

    public function test_generic_form_saves_transaction_without_audit(): void
    {
        $client = $this->client();
        $user = $this->scannerUser('scanner_generic.php');

        $this->logInAs($user);

        $this->post(route('scanners.generic.lookup'), ['action' => 'lookup', 'scanned' => 'DELA CRUZ, JUAN R'])
            ->assertJsonPath('data.id', $client->id);

        $this->post(route('scanners.generic.save'), [
            'client_id' => $client->id,
            'patient_option' => 'self',
            'program' => 'AICS',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'remarks' => 'assistance',
            'suggested_amount' => 3000,
            'status' => 'PENDING PAYOUT',
        ])->assertJson(['success' => true]);

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('AICS', $transaction->program);
        $this->assertSame('DELA CRUZ, JUAN R', $transaction->patient_name);
        $this->assertSame(3000.0, (float) $transaction->suggested_amount);

        $this->assertSame(0, DB::table('tbl_audit_logs')->count());
    }

    public function test_generic_form_supports_custom_patient_name(): void
    {
        $client = $this->client();
        $this->logInAs($this->scannerUser('scanner_generic.php'));

        $this->post(route('scanners.generic.save'), [
            'client_id' => $client->id,
            'patient_option' => 'custom',
            'patient_name_custom' => 'MARIA SANTOS',
            'program' => 'AKAP',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'status' => 'PAID',
        ])->assertJson(['success' => true]);

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('MARIA SANTOS', $transaction->patient_name);
        $this->assertSame('AKAP', $transaction->program);
    }
}
