<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\GipInfo;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GipTest extends TestCase
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
        $user = User::factory()->create(['username' => 'gip-clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'clients.php',
            'can_access' => true,
        ]);

        $this->logInAs($user);

        return $user;
    }

    private function gipTransaction(Client $client): Transaction
    {
        return Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'GIP',
            'patient_name' => $client->full_name,
            'date_applied' => '2026-08-01',
            'type' => 'Financial',
            'status' => 'PENDING',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->id,
            'valid_govt_id' => 'sss id',
            'id_number' => '12-3456789',
            'insurance_beneficiary' => 'juan dela cruz',
            'emergency_contact' => 'maria dela cruz',
            'ecp_contact_number' => '09171234567',
            'ecp_address' => 'vigan city',
            'college' => 'university of northern philippines',
            'course' => 'bachelor of science in information technology',
            'year_graduated' => '2025',
            'high_school' => 'ilocos sur national high school',
            'elementary_school' => 'vigan elementary school',
            'latest_work_experience' => "cashier at ABC store\n2020-2023",
            'position' => 'cashier',
            'period_of_engagement' => 'january 2025 - june 2025',
            'special_skills' => 'typing, driving',
            'achievements' => 'consistent honor student',
        ], $overrides);
    }

    public function test_gip_section_hidden_without_gip_transaction()
    {
        $user = $this->clientUser();
        $client = Client::factory()->create();

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertDontSee('GIP Details');
    }

    public function test_gip_section_shown_with_gip_transaction_and_details()
    {
        $user = $this->clientUser();
        $client = Client::factory()->create();
        $this->gipTransaction($client);
        $gip = GipInfo::query()->create([
            'client_id' => $client->id,
            'valid_govt_id' => 'SSS ID',
            'id_number' => '12-3456789',
            'college' => 'UNIVERSITY OF NORTHERN PHILIPPINES',
            'course' => 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY',
        ]);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('GIP Details')
            ->assertSee('SSS ID')
            ->assertSee('UNIVERSITY OF NORTHERN PHILIPPINES')
            ->assertSee('Edit GIP Details');
    }

    public function test_gip_details_can_be_added_with_audit()
    {
        $user = $this->clientUser();
        $client = Client::factory()->create();
        $this->gipTransaction($client);

        $response = $this->post(route('gip.store', $client), $this->payload($client));

        $response->assertRedirect(route('clients.show', $client).'#collapseGIP');
        $this->assertDatabaseHas('tbl_gip_info', [
            'client_id' => $client->id,
            'valid_govt_id' => 'SSS ID',
            'id_number' => '12-3456789',
            'insurance_beneficiary' => 'JUAN DELA CRUZ',
            'ecp_contact_number' => '09171234567',
            'college' => 'UNIVERSITY OF NORTHERN PHILIPPINES',
            'course' => 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY',
            'year_graduated' => '2025',
            'high_school' => 'ILOCOS SUR NATIONAL HIGH SCHOOL',
            'position' => 'CASHIER',
            'latest_work_experience' => "CASHIER AT ABC STORE\n2020-2023",
        ]);

        $this->assertDatabaseHas('tbl_audit_logs', [
            'user_id' => $user->id,
            'action' => 'ADD_GIP',
            'target_table' => 'tbl_clients',
            'target_id' => $client->id,
        ]);
    }

    public function test_gip_details_can_be_updated_and_logs_only_when_changed()
    {
        $user = $this->clientUser();
        $client = Client::factory()->create();
        $this->gipTransaction($client);
        $gip = GipInfo::query()->create([
            'client_id' => $client->id,
            'valid_govt_id' => 'SSS ID',
            'id_number' => '12-3456789',
        ]);

        $this->post(route('gip.store', $client), $this->payload($client, [
            'id_number' => '98-7654321',
        ]));

        $this->assertSame(1, GipInfo::query()->where('client_id', $client->id)->count());
        $this->assertDatabaseHas('tbl_gip_info', [
            'id' => $gip->id,
            'client_id' => $client->id,
            'id_number' => '98-7654321',
        ]);
        $this->assertDatabaseHas('tbl_audit_logs', [
            'user_id' => $user->id,
            'action' => 'UPDATE_GIP',
            'target_table' => 'tbl_clients',
            'target_id' => $client->id,
        ]);

        // Re-submitting identical data must not append another audit row.
        $this->post(route('gip.store', $client), $this->payload($client, [
            'id_number' => '98-7654321',
        ]));

        $this->assertSame(
            1,
            DB::table('tbl_audit_logs')
                ->where('user_id', $user->id)
                ->where('action', 'UPDATE_GIP')
                ->where('target_id', $client->id)
                ->count(),
        );
    }

    public function test_gip_store_requires_valid_client()
    {
        $this->clientUser();
        $client = Client::factory()->create();

        $this->post(route('gip.store', $client), [
            'client_id' => 999999,
            'valid_govt_id' => 'X',
        ])
            ->assertSessionHasErrors('client_id');

        $this->assertSame(0, GipInfo::count());
        $this->assertSame(0, DB::table('tbl_audit_logs')->count());
    }

    public function test_gip_store_requires_clients_permission()
    {
        $this->logInAs(User::factory()->create(['username' => 'no-access']));
        $client = Client::factory()->create();

        $this->post(route('gip.store', $client), $this->payload($client), ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
