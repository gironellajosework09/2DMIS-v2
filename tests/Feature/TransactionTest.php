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

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function transactionsUser(): User
    {
        $user = User::factory()->create(['username' => 'tclerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'all_transactions.php',
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

    public function test_transactions_page_is_gated_by_permission(): void
    {
        $this->logInAs(User::factory()->create(['username' => 'clerk']));

        $this->get(route('transactions.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    public function test_transactions_pages_load_for_permitted_user(): void
    {
        $client = $this->client();
        $transaction = Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'AICS',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'remarks' => 'TEST',
            'suggested_amount' => 5000.00,
            'status' => 'PENDING PAYOUT',
        ]);

        $this->logInAs($this->transactionsUser());

        $this->get(route('transactions.index'))->assertOk();
        $this->get(route('transactions.create', $client))->assertOk();
        $this->get(route('transactions.show', $transaction->id))->assertOk();
        $this->get(route('transactions.edit', $transaction->id))->assertOk();
    }

    public function test_transaction_can_be_created_with_self_patient_and_audit(): void
    {
        $client = $this->client();

        $this->logInAs($this->transactionsUser());

        $this->post(route('transactions.store'), [
            'client_id' => $client->id,
            'program' => 'AICS',
            'patient_option' => 'self',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'remarks' => 'medical assistance',
            'suggested_amount' => 5000,
            'status' => 'PENDING PAYOUT',
            'amount_paid' => 0,
        ])->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();

        $this->assertSame('AICS', $transaction->program);
        $this->assertSame('DELA CRUZ, JUAN R', $transaction->patient_name);
        $this->assertSame('MEDICAL ASSISTANCE', $transaction->remarks);
        $this->assertSame('PENDING PAYOUT', $transaction->status);

        $audit = DB::table('tbl_audit_logs')->where('action', 'ADD_TRANSACTION')->first();
        $this->assertNotNull($audit);
        $this->assertSame($transaction->id, (int) $audit->target_id);
        $this->assertStringContainsString('AICS', (string) $audit->new_value);
    }

    public function test_transaction_with_custom_patient_and_tupad_nulls_extra_fields(): void
    {
        $client = $this->client();

        $this->logInAs($this->transactionsUser());

        $this->post(route('transactions.store'), [
            'client_id' => $client->id,
            'program' => 'TUPAD',
            'patient_option' => 'custom',
            'patient_name_custom' => 'maria santos',
            'date_applied' => '2026-08-02',
            'type' => 'CASH FOR WORK',
            'remarks' => '',
            'comments' => 'should be nulled',
            'suggested_amount' => 4500,
            'status' => 'PENDING PAYOUT',
            'payout_date' => '2026-08-10',
            'gwa' => 1.25,
            'units' => 12,
        ])->assertRedirect();

        $transaction = Transaction::query()->firstOrFail();

        $this->assertSame('TUPAD', $transaction->program);
        $this->assertSame('MARIA SANTOS', $transaction->patient_name);
        $this->assertNull($transaction->comments);
        $this->assertNull($transaction->payout_date);
        $this->assertNull($transaction->gwa);
        $this->assertNull($transaction->units);
        $this->assertSame(4500.0, (float) $transaction->suggested_amount);
    }

    public function test_restricted_user_cannot_create_transaction_for_unpermitted_program(): void
    {
        $client = $this->client();
        $user = $this->transactionsUser();
        ProgramPermission::query()->create(['user_id' => $user->id, 'program_name' => 'AICS']);

        $this->logInAs($user);

        $this->post(route('transactions.store'), [
            'client_id' => $client->id,
            'program' => 'GIP',
            'patient_option' => 'self',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'status' => 'PENDING PAYOUT',
        ])->assertForbidden();

        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_transaction_can_be_updated_and_deleted_with_audits(): void
    {
        $client = $this->client();
        $transaction = Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'AICS',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'remarks' => 'OLD',
            'suggested_amount' => 5000.00,
            'status' => 'PENDING PAYOUT',
        ]);

        $this->logInAs($this->transactionsUser());

        $this->put(route('transactions.update', $transaction->id), [
            'program' => 'AICS',
            'patient_option' => 'self',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'remarks' => 'UPDATED REMARK',
            'suggested_amount' => 6000,
            'status' => 'PAID',
            'amount_paid' => 6000,
            'date_paid' => '2026-08-05',
        ])->assertRedirect(route('transactions.show', $transaction->id));

        $fresh = $transaction->fresh();
        $this->assertSame('UPDATED REMARK', $fresh->remarks);
        $this->assertSame('PAID', $fresh->status);

        $editAudit = DB::table('tbl_audit_logs')->where('action', 'EDIT_TRANSACTION')->first();
        $this->assertNotNull($editAudit);
        $this->assertStringContainsString('UPDATED REMARK', (string) $editAudit->new_value);

        $this->post(route('transactions.destroy', $transaction->id), [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNull(Transaction::query()->find($transaction->id));

        $deleteAudit = DB::table('tbl_audit_logs')->where('action', 'DELETE_TRANSACTION')->first();
        $this->assertNotNull($deleteAudit);
    }

    public function test_data_feed_returns_transactions_and_honors_filters(): void
    {
        $client = $this->client();
        Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'AICS',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'status' => 'PENDING PAYOUT',
            'suggested_amount' => 5000,
        ]);
        Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'AKAP',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-08-02',
            'type' => 'OCA',
            'status' => 'PAID',
            'suggested_amount' => 10000,
        ]);

        $this->logInAs($this->transactionsUser());

        $this->post(route('transactions.data'), ['draw' => 1])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonCount(2, 'data');

        $this->post(route('transactions.data'), ['draw' => 1, 'status' => 'PAID'])
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1);

        $this->post(route('transactions.data'), ['draw' => 1, 'program' => 'AICS'])
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1);
    }

    public function test_data_feed_enforces_program_restrictions(): void
    {
        $client = $this->client();
        Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'AICS',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'status' => 'PENDING PAYOUT',
        ]);
        Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'GIP',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-08-02',
            'type' => 'OCA',
            'status' => 'PAID',
        ]);

        $user = $this->transactionsUser();
        ProgramPermission::query()->create(['user_id' => $user->id, 'program_name' => 'AICS']);

        $this->logInAs($user);

        $this->post(route('transactions.data'), ['draw' => 1])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.program', 'AICS');

        $this->post(route('transactions.data'), ['draw' => 1, 'program' => 'GIP'])
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 0);
    }

    public function test_index_lists_only_permitted_programs_for_restricted_user(): void
    {
        $user = $this->transactionsUser();
        ProgramPermission::query()->create(['user_id' => $user->id, 'program_name' => 'AICS']);
        ProgramPermission::query()->create(['user_id' => $user->id, 'program_name' => 'TUPAD']);

        $this->logInAs($user);

        $this->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('value="AICS"', false)
            ->assertSee('value="TUPAD"', false)
            ->assertDontSee('value="GIP"');
    }

    public function test_transaction_csv_exports_stream_with_bom(): void
    {
        $client = $this->client();
        Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'AICS',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'remarks' => 'TEST',
            'suggested_amount' => 5000,
            'status' => 'PENDING PAYOUT',
        ]);

        $this->logInAs($this->transactionsUser());

        $response = $this->get(route('transactions.export', ['export_mode' => 'custom']));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('AICS', $content);
    }

    public function test_inline_update_updates_row_fields(): void
    {
        $client = $this->client();
        $transaction = Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'AICS',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'remarks' => 'OLD',
            'suggested_amount' => 5000.00,
            'status' => 'PENDING PAYOUT',
        ]);

        $this->logInAs($this->transactionsUser());

        $this->post(route('transactions.inline-update'), [
            'id' => $transaction->id,
            'remarks' => 'INLINE',
            'comments' => 'NOTE',
            'suggested_amount' => '6,500.00',
            'status' => 'PAID',
            'amount_paid' => '6500',
            'date_paid' => '08/05/2026',
            'gwa' => '1.50',
            'units' => '18',
        ])->assertOk()->assertJson(['success' => true]);

        $fresh = $transaction->fresh();
        $this->assertSame('INLINE', $fresh->remarks);
        $this->assertSame('PAID', $fresh->status);
        $this->assertSame(6500.0, (float) $fresh->suggested_amount);
        $this->assertSame('2026-08-05', $fresh->date_paid);
        $this->assertSame(1.5, (float) $fresh->gwa);
        $this->assertSame(18.0, (float) $fresh->units);

        $editAudit = DB::table('tbl_audit_logs')->where('action', 'EDIT_TRANSACTION')->first();
        $this->assertNotNull($editAudit);
    }

    public function test_beneficiary_client_search_returns_clients(): void
    {
        $client = $this->client();

        $this->logInAs($this->transactionsUser());

        $this->get(route('transactions.clients-search').'?q=JUAN')
            ->assertOk()
            ->assertJsonCount(1);
    }
}
