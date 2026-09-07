<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\ClientAffOrg;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create(['username' => 'clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'clients.php',
            'can_access' => true,
        ]);

        return $user;
    }

    /**
     * @return array{0: Municipality, 1: Barangay}
     */
    private function place(): array
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN']);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'BARANGAY I',
        ]);

        return [$municipality, $barangay];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Municipality $municipality, Barangay $barangay): array
    {
        return [
            'lastname' => 'dela cruz',
            'firstname' => 'juan',
            'middlename' => 'santos',
            'extensionname' => '',
            'city_municipality' => $municipality->id,
            'barangay' => $barangay->id,
            'house_no' => '12A',
            'mobile_no' => '09171234567',
            'email' => 'juan@example.com',
            'birthdate' => '1990-05-15',
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'pwd' => 'NO',
            'ip' => 'NO',
            'occupation' => 'farmer',
            'monthly_income' => '5000.50',
            'precinct_no' => '0001A',
            'voter_id' => 'V123456',
            'aff_org' => ['RIC', 'tala'],
        ];
    }

    public function test_clients_page_is_gated_by_permission(): void
    {
        $user = User::factory()->create(['username' => 'clerk']);

        $this->logInAs($user);

        $this->get(route('clients.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    public function test_clients_pages_load_for_permitted_user(): void
    {
        [$municipality, $barangay] = $this->place();
        $client = Client::query()->create([
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'city_municipality' => $municipality->id,
            'barangay' => $barangay->id,
            'birthdate' => '1990-05-15',
            'age' => 36,
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'category' => 'ADULT (30-59)',
            'aff_org' => '',
            'full_name' => 'DELA CRUZ, JUAN',
            'match_name' => 'DELACRUZ',
        ]);

        $this->logInAs($this->clientUser());

        $this->get(route('clients.index'))->assertOk();
        $this->get(route('clients.create'))->assertOk();
        $this->get(route('clients.edit', $client))->assertOk();
    }

    public function test_client_can_be_created_with_derived_fields(): void
    {
        [$municipality, $barangay] = $this->place();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.store'), $this->validPayload($municipality, $barangay))
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success');

        $client = Client::query()->firstOrFail();

        $this->assertSame('DELA CRUZ', $client->lastname);
        $this->assertSame('JUAN', $client->firstname);
        $this->assertSame('SANTOS', $client->middlename);
        $this->assertSame('DELA CRUZ, JUAN SANTOS', $client->full_name);
        $this->assertSame('DELACRUZJUANSANTOS', $client->match_name);
        $this->assertSame('Region I', $client->region);
        $this->assertSame('Ilocos Sur', $client->province);
        $this->assertSame((new ClientService)->deriveAge('1990-05-15'), $client->age);
        $this->assertSame('ADULT (30-59)', $client->category);
        $this->assertSame('5000.50', $client->monthly_income);
        $this->assertSame('FARMER', $client->occupation);

        $orgs = ClientAffOrg::query()->where('client_id', $client->id)->pluck('organization')->all();
        $this->assertSame(['RIC', 'TALA'], $orgs);

        $audit = DB::table('tbl_audit_logs')->where('action', 'ADD_CLIENT')->first();
        $this->assertNotNull($audit);
        $this->assertSame($client->id, $audit->target_id);
        $this->assertNull($audit->old_value);
        $this->assertStringContainsString('DELA CRUZ, JUAN SANTOS', (string) $audit->new_value);
    }

    public function test_client_creation_requires_required_fields(): void
    {
        $this->logInAs($this->clientUser());

        $this->post(route('clients.store'), [])
            ->assertSessionHasErrors(['lastname', 'firstname', 'city_municipality', 'barangay', 'birthdate', 'sex', 'civil_status']);

        $this->assertSame(0, Client::query()->count());
    }

    public function test_client_can_be_edited_and_audited(): void
    {
        [$municipality, $barangay] = $this->place();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.store'), $this->validPayload($municipality, $barangay));

        $client = Client::query()->firstOrFail();
        $id = $client->id;

        $payload = $this->validPayload($municipality, $barangay);
        $payload['lastname'] = 'reyes';
        $payload['middlename'] = '';
        $payload['aff_org'] = ['LCW'];

        $this->put(route('clients.update', $client), $payload)
            ->assertRedirect(route('clients.index'));

        $client = $client->fresh();

        $this->assertSame('REYES', $client->lastname);
        $this->assertSame('REYES, JUAN', $client->full_name);
        $this->assertSame('REYESJUAN', $client->match_name);

        $orgs = ClientAffOrg::query()->where('client_id', $id)->pluck('organization')->all();
        $this->assertSame(['LCW'], $orgs);

        $audit = DB::table('tbl_audit_logs')->where('action', 'EDIT_CLIENT')->first();
        $this->assertNotNull($audit);
        $this->assertSame($id, $audit->target_id);
        $this->assertStringContainsString('DELA CRUZ', (string) $audit->old_value);
        $this->assertStringContainsString('REYES', (string) $audit->new_value);
    }

    public function test_client_data_feed_returns_rows(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->logInAs($this->clientUser());
        $this->post(route('clients.store'), $this->validPayload($municipality, $barangay));

        $this->post(route('clients.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.fullname', 'DELA CRUZ, JUAN SANTOS');

        $this->post(route('clients.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'municipality' => $municipality->id,
        ])
            ->assertJsonPath('recordsFiltered', 1);

        $this->post(route('clients.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => 'VIGAN'],
        ])
            ->assertJsonPath('recordsFiltered', 1);
    }

    public function test_client_data_feed_searches_by_precinct_no(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->logInAs($this->clientUser());
        $this->post(route('clients.store'), $this->validPayload($municipality, $barangay));

        // The single search field searches precinct no. too. DataTables-standard
        // requests send `search` as a { value } object...
        $this->post(route('clients.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25,
            'search' => ['value' => '0001A'],
        ])
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.fullname', 'DELA CRUZ, JUAN SANTOS');

        // ...while the clients screen sends `search` as a plain top-level string.
        // Both shapes must be accepted (no "Array to string conversion" and both
        // must find the client by precinct / by last name).
        $this->post(route('clients.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25,
            'search' => '0001A',
        ])
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.fullname', 'DELA CRUZ, JUAN SANTOS');

        $this->post(route('clients.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25,
            'search' => 'cru',
        ])
            ->assertJsonPath('recordsFiltered', 1);

        // A non-matching precinct yields no rows.
        $this->post(route('clients.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25,
            'search' => 'ZZ-999-NOPE',
        ])
            ->assertJsonPath('recordsFiltered', 0);
    }

    public function test_geography_barangays_returns_json(): void
    {
        [$municipality] = $this->place();

        $this->logInAs($this->clientUser());

        $this->get(route('geography.barangays').'?municipality_id='.$municipality->id)
            ->assertOk()
            ->assertJsonFragment(['name' => 'BARANGAY I']);

        $this->get(route('geography.barangays').'?municipality_id=999')
            ->assertStatus(302)
            ->assertSessionHasErrors('municipality_id');
    }

    public function test_client_data_feed_filters_by_category(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->logInAs($this->clientUser());

        $p1 = $this->validPayload($municipality, $barangay);
        $this->post(route('clients.store'), $p1); // birthdate 1990-05-15 -> ADULT (30-59)

        $p2 = $this->validPayload($municipality, $barangay);
        $p2['lastname'] = 'santos';
        $p2['birthdate'] = '2000-06-01'; // -> YOUTH (18-29)
        $this->post(route('clients.store'), $p2);

        $this->post(route('clients.data'), [
            'draw' => 1, 'start' => 0, 'length' => 25,
            'category' => 'ADULT (30-59)',
        ])
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.fullname', 'DELA CRUZ, JUAN SANTOS');
    }

    public function test_clients_index_renders_category_filter(): void
    {
        $this->logInAs($this->clientUser());

        $html = $this->get(route('clients.index'))
            ->assertOk()
            ->getContent();

        // Fourth segment button (Category) is present and wired to the shared
        // FilterChips category of the same key.
        $this->assertStringContainsString('data-filter-segment="category"', $html);

        // The authoritative category options (deriveCategory values) are
        // server-rendered into the shared FilterChips menu.
        foreach (ClientService::CATEGORIES as $category) {
            $this->assertStringContainsString($category, $html);
        }
    }

    public function test_client_can_be_deleted_and_audited(): void
    {
        [$municipality, $barangay] = $this->place();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.store'), $this->validPayload($municipality, $barangay));

        $client = Client::query()->firstOrFail();
        $id = $client->id;

        $this->post(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success');

        $this->assertNull(Client::query()->find($id));

        $audit = DB::table('tbl_audit_logs')->where('action', 'DELETE_CLIENT')->first();
        $this->assertNotNull($audit);
        $this->assertSame($id, $audit->target_id);
        $this->assertStringContainsString('DELA CRUZ', (string) $audit->old_value);
        $this->assertNull($audit->new_value);
    }

    public function test_client_details_panel_returns_partial_without_layout(): void
    {
        [$municipality, $barangay] = $this->place();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.store'), $this->validPayload($municipality, $barangay));

        $client = Client::query()->firstOrFail();

        $this->get(route('clients.show', $client).'?panel=1')
            ->assertOk()
            ->assertSee('Client Profile')
            ->assertSee($client->full_name)
            ->assertDontSee('<html', false);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('<html', false);
    }

    public function test_client_with_transactions_cannot_be_deleted(): void
    {
        [$municipality, $barangay] = $this->place();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.store'), $this->validPayload($municipality, $barangay));

        $client = Client::query()->firstOrFail();

        Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'AICS',
            'patient_name' => 'DELA CRUZ, JUAN SANTOS',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'status' => 'PENDING PAYOUT',
        ]);

        $this->post(route('clients.destroy', $client))
            ->assertSessionHasErrors('delete');

        $this->assertNotNull(Client::query()->find($client->id));
    }

    public function test_create_modal_fragment_returns_form_without_layout(): void
    {
        $this->logInAs($this->clientUser());

        $this->get(route('clients.create').'?modal=1')
            ->assertOk()
            ->assertSee('Last Name')
            ->assertSee('Personal Information')
            // UX refinement (§11/§14): the Add/Edit modal is a fixed
            // header + scrollable body + fixed gray footer. The modal
            // fragment renders ONLY the grouped form fields, owned by the
            // fixed footer's submit (button bound via form="clientForm",
            // which lives in clients.index, not this fragment). No inline
            // form footer renders in modal mode.
            ->assertSee('id="clientForm"', false)
            ->assertDontSee('Cancel / Return')
            ->assertDontSee('<html', false);
    }

    public function test_edit_modal_fragment_returns_form_without_layout(): void
    {
        [$municipality, $barangay] = $this->place();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.store'), $this->validPayload($municipality, $barangay));

        $client = Client::query()->firstOrFail();

        $this->get(route('clients.edit', $client).'?modal=1')
            ->assertOk()
            ->assertSee('First Name')
            ->assertSee('id="clientForm"', false)
            ->assertDontSee('Cancel / Return')
            ->assertDontSee('<html', false);
    }

    public function test_clients_index_renders_fixed_modal_footer_and_submit(): void
    {
        $this->logInAs($this->clientUser());

        // The Add/Edit modal's primary action lives in a fixed gray footer
        // (not inside the scrollable body or the injected fragment), wired to
        // the in-body form via the form attribute.
        $this->get(route('clients.index'))
            ->assertOk()
            ->assertSee('id="clientFormSubmit"', false)
            ->assertSee('form="clientForm"', false);
    }

    public function test_clients_index_has_exactly_one_search_input_and_six_columns(): void
    {
        $this->logInAs($this->clientUser());

        $html = $this->get(route('clients.index'))
            ->assertOk()
            ->getContent();

        // Exactly one client search control; DataTables built-in filter box
        // (dom omits 'f') must not expose a second search input on this screen.
        $this->assertSame(1, substr_count($html, 'id="clientsSearch"'));

        // Six visible columns: Client, Precinct, Municipality, Barangay,
        // Category, plus the empty Actions header.
        $matches = [];
        preg_match_all('/<th[^>]*>(.*?)<\/th>/is', $html, $matches);
        $headers = array_map(fn ($h) => trim(strip_tags($h)), $matches[1] ?? []);
        $this->assertSame(['Client', 'Precinct No', 'Municipality', 'Barangay', 'Category', ''], $headers);
    }

    public function test_clients_index_renders_each_filter_pill_with_its_own_popover_section(): void
    {
        $this->logInAs($this->clientUser());

        $html = $this->get(route('clients.index'))
            ->assertOk()
            ->getContent();

        // Each per-filter pill in the client toolbar maps to a category section
        // inside the shared FilterChips popover (BUG 1 contract: clicking a pill
        // opens a focused popover for ITS OWN options, not a single shared menu).
        foreach (['municipality', 'barangay', 'program', 'category'] as $key) {
            $this->assertStringContainsString('data-filter-segment="'.$key.'"', $html);
            $this->assertStringContainsString('data-filter-cat="'.$key.'"', $html);
        }

        // The share component's popover options are server-rendered for
        // municipality (ACL-scoped) and category (static) so a click has real
        // choices immediately — no client-side fetch required to see them.
        $this->assertStringContainsString(
            'data-filter-options="municipality"',
            $html,
        );
    }

    public function test_store_responds_to_json_request_with_success(): void
    {
        [$municipality, $barangay] = $this->place();

        $this->logInAs($this->clientUser());

        $response = $this->postJson(route('clients.store'), $this->validPayload($municipality, $barangay))
            ->assertOk()
            ->assertJsonPath('success', true);

        $client = Client::query()->firstOrFail();
        $this->assertStringContainsString('DELA CRUZ, JUAN SANTOS', (string) $response->json('message'));
        $this->assertSame($client->id, $response->json('client_id'));
    }

    public function test_store_json_request_surfaces_validation_errors(): void
    {
        [$municipality, $barangay] = $this->place();

        $this->logInAs($this->clientUser());

        $this->postJson(route('clients.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lastname', 'firstname', 'city_municipality', 'barangay', 'birthdate', 'sex', 'civil_status']);

        $this->assertSame(0, Client::query()->count());
    }

    public function test_update_responds_to_json_request_with_success(): void
    {
        [$municipality, $barangay] = $this->place();

        $this->logInAs($this->clientUser());

        $this->post(route('clients.store'), $this->validPayload($municipality, $barangay));

        $client = Client::query()->firstOrFail();

        $payload = $this->validPayload($municipality, $barangay);
        $payload['lastname'] = 'reyes';

        $this->putJson(route('clients.update', $client), $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('id', $client->id);

        $this->assertSame('REYES', $client->fresh()->lastname);
    }
}
