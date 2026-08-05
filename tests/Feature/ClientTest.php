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
}
