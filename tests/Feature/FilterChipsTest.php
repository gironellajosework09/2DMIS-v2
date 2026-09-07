<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\ProgramPermission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserMunicipality;
use App\Services\AccessControlService;
use App\Support\FilterConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2C — shared FilterChips component.
 *
 * Exercises the server-side contracts that the FilterChips JS sits on top of:
 *   - multi-select OR within a category (comma-split whereIn) and AND across
 *     categories;
 *   - URL persistence / deep-link restoration and active-filter-state rendering
 *     (chip removal + Clear All are JS, but their state source is the URL,
 *     which is what these tests lock down);
 *   - municipality -> barangay cascade config (dependsOn + per-option muni);
 *   - ACL option parity: municipality options stay ACL-scoped on the tiny feed
 *     modules (Clients/Households/Transactions) and unscoped on the
 *     non-scoped v1 modules (Scholarship/Payouts/Unpaid);
 *   - feed ACL enforcement happens before user filters (hostile param cannot
 *     widen);
 *   - program permission filtering on Transactions;
 *   - single-value filters stay backward compatible;
 *   - zero-result filters do not break the feed;
 *   - Audit Logs preserves its client-side model (user/action options fed from
 *     the feed, min/max date range).
 */
class FilterChipsTest extends TestCase
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

    private function enforce(string $pageName): void
    {
        $pages = config('authorization.pages');
        $pages[$pageName]['enforcement'] = true;
        config(['authorization.pages' => $pages]);
    }

    /** @return array{0: Municipality, 1: Barangay} */
    private function place(string $name): array
    {
        $municipality = Municipality::query()->create(['name' => $name, 'code' => strtoupper(substr($name, 0, 3))]);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'BARANGAY I']);

        return [$municipality, $barangay];
    }

    private function client(int $municipalityId, int $barangayId, string $lastname = 'DELA CRUZ'): Client
    {
        return Client::query()->create([
            'lastname' => $lastname,
            'firstname' => 'JUAN',
            'middlename' => 'R',
            'city_municipality' => $municipalityId,
            'barangay' => $barangayId,
            'birthdate' => '1990-05-15',
            'age' => 36,
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'category' => 'ADULT (30-59)',
            'aff_org' => '',
            'mobile_no' => '09171234567',
            'full_name' => strtoupper($lastname).', JUAN R',
            'match_name' => strtoupper($lastname).'JUANR',
        ]);
    }

    private function transaction(int $clientId, string $program, string $status = 'PENDING PAYOUT', array $extra = []): Transaction
    {
        return Transaction::query()->create(array_merge([
            'client_id' => $clientId,
            'program' => $program,
            'patient_name' => 'JUAN R DELA CRUZ',
            'date_applied' => $extra['date_applied'] ?? '2026-08-01',
            'type' => 'OCA',
            'status' => $status,
        ], $extra));
    }

    private function clientsUser(): User
    {
        $user = User::factory()->create(['username' => 'fc-clients']);
        $this->grantPage($user, 'clients.php');

        return $user;
    }

    private function householdsUser(): User
    {
        $user = User::factory()->create(['username' => 'fc-households']);
        $this->grantPage($user, 'household.php');

        return $user;
    }

    private function transactionsUser(): User
    {
        $user = User::factory()->create(['username' => 'fc-transactions']);
        $this->grantPage($user, 'all_transactions.php');

        return $user;
    }

    private function scholarshipUser(): User
    {
        $user = User::factory()->create(['username' => 'fc-scholarship']);
        $this->grantPage($user, 'scholarship_reports.php');

        return $user;
    }

    private function payoutUser(string $page = 'scanned_payouts.php'): User
    {
        $user = User::factory()->create(['username' => 'fc-payout']);
        $this->grantPage($user, $page);

        return $user;
    }

    private function unpaidUser(): User
    {
        $user = User::factory()->create(['username' => 'fc-unpaid']);
        $this->grantPage($user, 'unpaid_verifications.php');

        return $user;
    }

    private function auditUser(): User
    {
        $user = User::factory()->create(['username' => 'fc-audit']);
        $this->grantPage($user, '*');

        return $user;
    }

    /** Give the user the reserved ALL marker scope so every municipality is offered. */
    private function grantAllMunicipalities(User $user): void
    {
        UserMunicipality::query()->create([
            'user_id' => $user->id,
            'municipality_id' => AccessControlService::ALL_MUNICIPALITY_MARKER,
        ]);
    }

    /**
     * The Blade partial renders `@if($checked) checked @endif` as its own line,
     * so `checked` may be separated from data-filter-check by a newline + spaces.
     */
    private function assertChipChecked(string $content, string $catKey, string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/data-filter-check="'.preg_quote($catKey.'|'.$value, '/').'"\s+checked/',
            $content
        );
    }

    private function assertChipNotChecked(string $content, string $catKey, string $value): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/data-filter-check="'.preg_quote($catKey.'|'.$value, '/').'"\s+checked/',
            $content
        );
    }

    /*
     * ---------------------------------------------------------------
     * 1. Multi-select OR within one category
     * ---------------------------------------------------------------
     */

    public function test_clients_feed_multi_municipality_uses_or(): void
    {
        [$muniA] = $this->place('VIGAN');
        [$muniB] = $this->place('CANDON');
        [$muniOther, $barOther] = $this->place('NARVACAN');

        $this->client($muniA->id, 1);
        $this->client($muniB->id, 1);
        $this->client($muniOther->id, $barOther->id);

        $this->logInAs($this->clientsUser());

        $base = $this->post(route('clients.data'), ['draw' => 1])
            ->assertOk()
            ->json();

        $this->assertSame(3, $base['recordsFiltered']);

        $filtered = $this->post(route('clients.data'), [
            'draw' => 1,
            'municipality' => $muniA->id.','.$muniB->id,
        ])->assertOk()->json();

        $this->assertSame(2, $filtered['recordsFiltered']);
    }

    public function test_transactions_feed_multi_program_uses_or(): void
    {
        [$muni, $bar] = $this->place('VIGAN');
        $client = $this->client($muni->id, $bar->id);

        $this->transaction($client->id, 'AICS');
        $this->transaction($client->id, 'AKAP');
        $this->transaction($client->id, 'TUPAD');

        $this->logInAs($this->transactionsUser());

        $ok = $this->post(route('transactions.data'), ['draw' => 1])
            ->assertOk()
            ->json();
        $this->assertSame(3, $ok['recordsFiltered']);

        $or = $this->post(route('transactions.data'), ['draw' => 1, 'program' => 'AICS,AKAP'])
            ->assertOk()
            ->json();
        $this->assertSame(2, $or['recordsFiltered']);
    }

    /*
     * ---------------------------------------------------------------
     * 2. AND across different categories
     * ---------------------------------------------------------------
     */

    public function test_clients_feed_municipality_and_barangay_compose_and(): void
    {
        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB, $barB] = $this->place('CANDON');

        $barA2 = Barangay::query()->create(['municipality_id' => $muniA->id, 'name' => 'BARANGAY II']);

        $this->client($muniA->id, $barA->id);
        $this->client($muniA->id, $barA2->id);
        $this->client($muniB->id, $barB->id);

        $this->logInAs($this->clientsUser());

        // municipality=VIGAN AND barangay=BARANGAY I  (VIGAN only, barangay I only)
        $and = $this->post(route('clients.data'), [
            'draw' => 1,
            'municipality' => (string) $muniA->id,
            'barangay' => (string) $barA->id,
        ])->assertOk()->json();

        $this->assertSame(1, $and['recordsFiltered']);
    }

    public function test_transactions_feed_date_range_and_program_compose_and(): void
    {
        [$muni, $bar] = $this->place('VIGAN');
        $client = $this->client($muni->id, $bar->id);

        $this->transaction($client->id, 'AICS', 'PENDING PAYOUT', ['date_applied' => '2026-01-10']);
        $this->transaction($client->id, 'AICS', 'PENDING PAYOUT', ['date_applied' => '2026-03-10']);
        $this->transaction($client->id, 'AKAP', 'PENDING PAYOUT', ['date_applied' => '2026-02-10']);

        $this->logInAs($this->transactionsUser());

        // program=AICS AND date_applied in [2026-02-01, 2026-04-01]
        $and = $this->post(route('transactions.data'), [
            'draw' => 1,
            'program' => 'AICS',
            'date_applied_start' => '2026-02-01',
            'date_applied_end' => '2026-04-01',
        ])->assertOk()->json();

        $this->assertSame(1, $and['recordsFiltered']);
    }

    /*
     * ---------------------------------------------------------------
     * 3. URL persistence / deep-link restoration + 5. Clear All state
     * ---------------------------------------------------------------
     */

    public function test_clients_index_restores_selected_chips_from_url(): void
    {
        [$muni, $bar] = $this->place('VIGAN');

        $user = $this->clientsUser();
        $this->grantAllMunicipalities($user);
        $this->logInAs($user);

        $content = $this->get(route('clients.index', ['municipality' => $muni->id, 'barangay' => $bar->id]))
            ->assertOk()
            ->getContent();

        $this->assertChipChecked($content, 'municipality', (string) $muni->id);
        $this->assertChipChecked($content, 'barangay', (string) $bar->id);
    }

    public function test_clients_index_shows_no_active_filters_without_query(): void
    {
        $user = $this->clientsUser();
        $this->grantAllMunicipalities($user);
        $this->logInAs($user);

        // Clear All removes the query params, so the restored page has zero
        // active filters: the count badge and Clear All control are hidden.
        $content = $this->get(route('clients.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/data-filter-count[^>]*hidden/',
            $content
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-filter-check="[^"]+"\s+checked/',
            $content
        );
    }

    /*
     * ---------------------------------------------------------------
     * 4. Chip removal: removing a param drops it from active state
     * ---------------------------------------------------------------
     */

    public function test_clients_index_chip_removal_drops_param_from_active_state(): void
    {
        [$muni, $bar] = $this->place('VIGAN');

        $user = $this->clientsUser();
        $this->grantAllMunicipalities($user);
        $this->logInAs($user);

        // With barangay present: both chips active.
        $with = $this->get(route('clients.index', ['municipality' => $muni->id, 'barangay' => $bar->id]))
            ->assertOk()
            ->getContent();
        $this->assertChipChecked($with, 'municipality', (string) $muni->id);
        $this->assertChipChecked($with, 'barangay', (string) $bar->id);

        // After the chip is removed the URL drops barangay -> only municipality remains.
        $without = $this->get(route('clients.index', ['municipality' => $muni->id]))
            ->assertOk()
            ->getContent();
        $this->assertChipChecked($without, 'municipality', (string) $muni->id);
        $this->assertChipNotChecked($without, 'barangay', (string) $bar->id);
    }

    /*
     * ---------------------------------------------------------------
     * 6. Municipality -> Barangay cascade config
     * ---------------------------------------------------------------
     */

    public function test_clients_index_emits_cascade_config_for_barangay(): void
    {
        [$muniA] = $this->place('VIGAN');
        [$muniB] = $this->place('CANDON');
        $barA = Barangay::query()->create(['municipality_id' => $muniA->id, 'name' => 'BARANGAY I']);
        $barB = Barangay::query()->create(['municipality_id' => $muniB->id, 'name' => 'BARANGAY I']);

        $user = $this->clientsUser();
        $this->grantAllMunicipalities($user);
        $this->logInAs($user);

        $content = $this->get(route('clients.index'))->assertOk()->getContent();

        // The barangay category depends on municipality (drives the cascade).
        $this->assertMatchesRegularExpression(
            '/data-filter-cat="barangay"[^>]*data-filter-depends="municipality"/',
            $content
        );

        // Each option carries its parent municipality for client-side sanitization.
        $this->assertStringContainsString('data-filter-value="'.$barA->id.'"', $content);
        $this->assertStringContainsString('data-filter-muni="'.$muniA->id.'"', $content);
        $this->assertStringContainsString('data-filter-value="'.$barB->id.'"', $content);
        $this->assertStringContainsString('data-filter-muni="'.$muniB->id.'"', $content);
    }

    /*
     * ---------------------------------------------------------------
     * 7a. ACL parity — scoped modules limit municipality OPTIONS
     * ---------------------------------------------------------------
     */

    public function test_clients_municipality_options_respect_scope(): void
    {
        [$muniA] = $this->place('VIGAN');
        [$muniB] = $this->place('CANDON');

        $user = $this->clientsUser();
        UserMunicipality::query()->create(['user_id' => $user->id, 'municipality_id' => $muniA->id]);

        $this->logInAs($user);

        $content = $this->get(route('clients.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-filter-label="'.$muniA->name.'"', $content);
        $this->assertStringNotContainsString('data-filter-label="'.$muniB->name.'"', $content);
    }

    public function test_households_municipality_options_respect_scope(): void
    {
        [$muniA] = $this->place('VIGAN');
        [$muniB] = $this->place('CANDON');

        $user = $this->householdsUser();
        UserMunicipality::query()->create(['user_id' => $user->id, 'municipality_id' => $muniA->id]);

        $this->logInAs($user);

        $content = $this->get(route('households.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-filter-label="'.$muniA->name.'"', $content);
        $this->assertStringNotContainsString('data-filter-label="'.$muniB->name.'"', $content);
    }

    public function test_transactions_municipality_options_respect_scope(): void
    {
        [$muniA] = $this->place('VIGAN');
        [$muniB] = $this->place('CANDON');

        $user = $this->transactionsUser();
        UserMunicipality::query()->create(['user_id' => $user->id, 'municipality_id' => $muniA->id]);

        $this->logInAs($user);

        $content = $this->get(route('transactions.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-filter-label="'.$muniA->name.'"', $content);
        $this->assertStringNotContainsString('data-filter-label="'.$muniB->name.'"', $content);
    }

    /*
     * ---------------------------------------------------------------
     * 7a. ACL enforcement — hostile param cannot widen the feed
     * ---------------------------------------------------------------
     */

    public function test_clients_feed_scope_applies_before_user_municipality_filter(): void
    {
        $this->enforce('clients.php');

        [$muniIn] = $this->place('VIGAN');
        [$muniOut] = $this->place('CANDON');
        $nar = $this->place('NARVACAN');

        $this->client($muniIn->id, 1);
        $this->client($muniIn->id, 1);
        $this->client($muniOut->id, $nar[1]->id);

        $user = $this->clientsUser();
        UserMunicipality::query()->create(['user_id' => $user->id, 'municipality_id' => $muniIn->id]);

        $this->logInAs($user);

        // User is scoped to VIGAN; an out-of-scope municipality query must
        // never widen the set (scope is applied before the user filter).
        $narrow = $this->post(route('clients.data'), [
            'draw' => 1,
            'municipality' => (string) $muniOut->id,
        ])->assertOk()->json();

        $this->assertSame(0, $narrow['recordsFiltered']);

        $wide = $this->post(route('clients.data'), ['draw' => 1])->assertOk()->json();
        $this->assertSame(2, $wide['recordsFiltered']);
    }

    /*
     * ---------------------------------------------------------------
     * 7b. ACL parity — non-scoped modules keep unscoped options
     * ---------------------------------------------------------------
     */

    public function test_scholarship_municipality_options_stay_unscoped(): void
    {
        [$muniA] = $this->place('VIGAN');
        [$muniB] = $this->place('CANDON');
        [$muniC] = $this->place('NARVACAN');

        $this->logInAs($this->scholarshipUser());

        $content = $this->get(route('scholarship-reports.index'))->assertOk()->getContent();

        // The scholarship feed is NOT municipality-scoped (v1), so every
        // municipality must be offered regardless of the user's scope rows.
        $this->assertStringContainsString('data-filter-label="'.$muniA->name.'"', $content);
        $this->assertStringContainsString('data-filter-label="'.$muniB->name.'"', $content);
        $this->assertStringContainsString('data-filter-label="'.$muniC->name.'"', $content);
    }

    public function test_payout_municipality_options_stay_unscoped(): void
    {
        [$muniA] = $this->place('VIGAN');
        [$muniB] = $this->place('CANDON');

        $this->logInAs($this->payoutUser());

        $content = $this->get(route('payout-attendance.scanned_payouts.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-filter-label="'.$muniA->name.'"', $content);
        $this->assertStringContainsString('data-filter-label="'.$muniB->name.'"', $content);
    }

    public function test_unpaid_municipality_options_stay_unscoped(): void
    {
        [$muniA] = $this->place('VIGAN');
        [$muniB] = $this->place('CANDON');

        $this->logInAs($this->unpaidUser());

        $content = $this->get(route('unpaid-verifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-filter-label="'.$muniA->name.'"', $content);
        $this->assertStringContainsString('data-filter-label="'.$muniB->name.'"', $content);
    }

    /*
     * ---------------------------------------------------------------
     * 8. Program permission filtering (Transactions options)
     * ---------------------------------------------------------------
     */

    public function test_transactions_program_options_are_permission_filtered(): void
    {
        $user = $this->transactionsUser();
        ProgramPermission::query()->create(['user_id' => $user->id, 'program_name' => 'AICS']);
        ProgramPermission::query()->create(['user_id' => $user->id, 'program_name' => 'TUPAD']);

        $this->logInAs($user);

        $content = $this->get(route('transactions.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-filter-label="AICS"', $content);
        $this->assertStringContainsString('data-filter-label="TUPAD"', $content);
        $this->assertStringNotContainsString('data-filter-label="AKAP"', $content);
    }

    public function test_transactions_feed_rejects_unpermitted_program_requests(): void
    {
        [$muni, $bar] = $this->place('VIGAN');
        $client = $this->client($muni->id, $bar->id);
        $this->transaction($client->id, 'AICS');
        $this->transaction($client->id, 'GIP');

        $user = $this->transactionsUser();
        ProgramPermission::query()->create(['user_id' => $user->id, 'program_name' => 'AICS']);

        $this->logInAs($user);

        // Requesting a program outside the user's grant must not widen (whereForbidden).
        $forbidden = $this->post(route('transactions.data'), ['draw' => 1, 'program' => 'GIP'])
            ->assertOk()
            ->json();

        $this->assertSame(0, $forbidden['recordsFiltered']);

        $granted = $this->post(route('transactions.data'), ['draw' => 1])
            ->assertOk()
            ->json();
        $this->assertSame(1, $granted['recordsFiltered']);
    }

    /*
     * ---------------------------------------------------------------
     * 9. Backward-compatible single-value filter
     * ---------------------------------------------------------------
     */

    public function test_single_value_filters_behave_like_before(): void
    {
        [$muniA, $barA] = $this->place('VIGAN');
        [$muniB] = $this->place('CANDON');

        $this->client($muniA->id, $barA->id);
        $this->client($muniA->id, $barA->id);
        $this->client($muniB->id, 1);

        $this->logInAs($this->clientsUser());

        $single = $this->post(route('clients.data'), ['draw' => 1, 'municipality' => (string) $muniA->id])
            ->assertOk()
            ->json();

        $this->assertSame(2, $single['recordsFiltered']);
    }

    public function test_apply_multi_value_single_uses_where_multi_uses_where_in(): void
    {
        $query = DB::table('tbl_clients');

        FilterConfig::applyMultiValue($query, 'city_municipality', '5');
        $this->assertStringContainsString('where', $query->toSql());
        $this->assertStringNotContainsString('in (', $query->toSql());

        $multi = DB::table('tbl_clients');
        FilterConfig::applyMultiValue($multi, 'city_municipality', '3,5,9');
        $this->assertStringContainsString('in (', $multi->toSql());

        // Empty value is a no-op.
        $empty = DB::table('tbl_clients');
        FilterConfig::applyMultiValue($empty, 'city_municipality', '');
        $this->assertSame('select * from `tbl_clients`', $empty->toSql());
    }

    /*
     * ---------------------------------------------------------------
     * 10. Zero-result filters do not break the feed
     * ---------------------------------------------------------------
     */

    public function test_zero_result_filters_return_empty_without_error(): void
    {
        [$muni, $bar] = $this->place('VIGAN');
        $this->client($muni->id, $bar->id);

        $this->logInAs($this->clientsUser());

        $payload = $this->post(route('clients.data'), ['draw' => 1, 'municipality' => '999999'])
            ->assertOk()
            ->json();

        $this->assertSame(0, $payload['recordsFiltered']);
        $this->assertCount(0, $payload['data']);

        $this->logInAs($this->transactionsUser());

        $tx = $this->post(route('transactions.data'), ['draw' => 1, 'status' => 'PAID', 'municipality' => '999999'])
            ->assertOk()
            ->json();
        $this->assertSame(0, $tx['recordsFiltered']);
    }

    /*
     * ---------------------------------------------------------------
     * 11. Transactions 8-param feed + GET deep-link compatibility
     * ---------------------------------------------------------------
     */

    public function test_transactions_deep_link_restores_all_eight_filter_chips(): void
    {
        [$muni, $bar] = $this->place('VIGAN');

        $user = $this->transactionsUser();
        $this->grantAllMunicipalities($user);
        $this->logInAs($user);

        $content = $this->get(route('transactions.index', [
            'program' => 'AICS',
            'status' => 'PAID',
            'municipality' => $muni->id,
            'barangay' => $bar->id,
            'date_applied_start' => '2026-01-01',
            'date_applied_end' => '2026-12-31',
            'date_paid_start' => '2026-01-01',
            'date_paid_end' => '2026-12-31',
        ]))
            ->assertOk()
            ->getContent();

        $this->assertChipChecked($content, 'program', 'AICS');
        $this->assertChipChecked($content, 'status', 'PAID');
        $this->assertChipChecked($content, 'municipality', (string) $muni->id);
        $this->assertChipChecked($content, 'barangay', (string) $bar->id);

        // Date-range inputs are restored from the query (the applied and paid ranges).
        $this->assertMatchesRegularExpression('/data-filter-date-start="[^"]+"\s+value="2026-01-01"/', $content);
        $this->assertMatchesRegularExpression('/data-filter-date-end="[^"]+"\s+value="2026-12-31"/', $content);
    }

    public function test_transactions_feed_honors_status_program_and_dates_together(): void
    {
        [$muni, $bar] = $this->place('VIGAN');
        $client = $this->client($muni->id, $bar->id);

        $this->transaction($client->id, 'AICS', 'PAID', ['date_applied' => '2026-08-01', 'date_paid' => '2026-08-05']);
        $this->transaction($client->id, 'AICS', 'PENDING PAYOUT', ['date_applied' => '2026-08-01']);
        $this->transaction($client->id, 'AKAP', 'PAID', ['date_applied' => '2026-08-01', 'date_paid' => '2026-08-05']);

        $this->logInAs($this->transactionsUser());

        $payload = $this->post(route('transactions.data'), [
            'draw' => 1,
            'program' => 'AICS',
            'status' => 'PAID',
            'date_paid_start' => '2026-08-05',
            'date_paid_end' => '2026-08-05',
        ])->assertOk()->json();

        $this->assertSame(1, $payload['recordsFiltered']);
    }

    /*
     * ---------------------------------------------------------------
     * 12. Audit Logs keeps its client-side filtering model
     * ---------------------------------------------------------------
     */

    public function test_audit_index_emits_client_side_filter_config(): void
    {
        $this->logInAs($this->auditUser());

        $content = $this->get(route('admin.audit-logs.index'))->assertOk()->getContent();

        // user/action are client-filtered categories; options start empty and
        // are fed from the feed response (setOptions), min/max date range.
        $this->assertMatchesRegularExpression('/data-filter-cat="user"/', $content);
        $this->assertMatchesRegularExpression('/data-filter-cat="action"/', $content);
        $this->assertMatchesRegularExpression('/data-filter-startparam="minDate"/', $content);
        $this->assertMatchesRegularExpression('/data-filter-endparam="maxDate"/', $content);
    }

    public function test_audit_feed_returns_client_side_option_sources(): void
    {
        $user = $this->auditUser();

        $this->logInAs($user);

        $payload = $this->post(route('admin.audit-logs.data'))
            ->assertOk()
            ->json();

        // The client consumes users/actions to populate the FilterChips options.
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('users', $payload);
        $this->assertArrayHasKey('actions', $payload);
    }
}
