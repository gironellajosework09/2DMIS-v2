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

class DuplicateTest extends TestCase
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

    private function makeClient(int $municipalityId, int $barangayId, string $last, string $first, string $middle = 'S'): Client
    {
        return Client::query()->create([
            'lastname' => $last,
            'firstname' => $first,
            'middlename' => $middle,
            'city_municipality' => $municipalityId,
            'barangay' => $barangayId,
            'birthdate' => '1990-05-15',
            'age' => 36,
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'category' => 'ADULT (30-59)',
            'aff_org' => '',
            'full_name' => "$last, $first $middle",
            'match_name' => strtoupper($last.$first.$middle),
        ]);
    }

    public function test_duplicates_page_is_gated_by_permission(): void
    {
        $this->logInAs(User::factory()->create(['username' => 'clerk']));

        $this->get(route('duplicates.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('login_status', 'denied');
    }

    public function test_duplicates_page_and_feed_return_duplicate_rows(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->makeClient($municipality->id, $barangay->id, 'DELA CRUZ', 'JUAN');
        $this->makeClient($municipality->id, $barangay->id, 'DELA CRUZ', 'JUAN');
        $this->makeClient($municipality->id, $barangay->id, 'REYES', 'MARIA');

        $this->logInAs($this->clientUser());

        $this->get(route('duplicates.index'))->assertOk();

        $response = $this->post(route('duplicates.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $row) {
            $this->assertStringContainsString('name="delete_ids[]"', $row[0]);
        }
    }

    public function test_duplicates_feed_filters_by_municipality(): void
    {
        $firstMuni = Municipality::query()->create(['name' => 'VIGAN']);
        $firstBrgy = Barangay::query()->create(['municipality_id' => $firstMuni->id, 'name' => 'BARANGAY I']);
        $this->makeClient($firstMuni->id, $firstBrgy->id, 'DELA CRUZ', 'JUAN');
        $this->makeClient($firstMuni->id, $firstBrgy->id, 'DELA CRUZ', 'JUAN');

        $secondMuni = Municipality::query()->create(['name' => 'CANDON']);
        $secondBrgy = Barangay::query()->create(['municipality_id' => $secondMuni->id, 'name' => 'BARANGAY II']);
        $this->makeClient($secondMuni->id, $secondBrgy->id, 'DELA CRUZ', 'JUAN');
        $this->makeClient($secondMuni->id, $secondBrgy->id, 'DELA CRUZ', 'JUAN');

        $this->logInAs($this->clientUser());

        $this->post(route('duplicates.data'), [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'municipality' => $firstMuni->id,
        ])
            ->assertJsonPath('recordsFiltered', 2);
    }

    public function test_delete_selected_removes_duplicates_and_audits(): void
    {
        [$municipality, $barangay] = $this->place();
        $first = $this->makeClient($municipality->id, $barangay->id, 'DELA CRUZ', 'JUAN');
        $second = $this->makeClient($municipality->id, $barangay->id, 'DELA CRUZ', 'JUAN');

        $this->logInAs($this->clientUser());

        $this->post(route('duplicates.destroy'), ['delete_ids' => [$second->id]])
            ->assertRedirect(route('duplicates.index'))
            ->assertSessionHas('success', '1 record(s) deleted.');

        $this->assertNotNull(Client::query()->find($first->id));
        $this->assertNull(Client::query()->find($second->id));

        $audit = DB::table('tbl_audit_logs')->where('action', 'DELETE_CLIENT')->first();
        $this->assertNotNull($audit);
        $this->assertSame($second->id, $audit->target_id);
    }

    public function test_delete_requires_selection(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->makeClient($municipality->id, $barangay->id, 'DELA CRUZ', 'JUAN');
        $this->makeClient($municipality->id, $barangay->id, 'DELA CRUZ', 'JUAN');

        $this->logInAs($this->clientUser());

        $this->post(route('duplicates.destroy'), ['delete_ids' => []])
            ->assertSessionHasErrors('delete');
    }

    public function test_delete_skips_duplicates_with_transactions(): void
    {
        [$municipality, $barangay] = $this->place();
        $withTx = $this->makeClient($municipality->id, $barangay->id, 'DELA CRUZ', 'JUAN');
        $clean = $this->makeClient($municipality->id, $barangay->id, 'DELA CRUZ', 'JUAN');

        Transaction::query()->create([
            'client_id' => $withTx->id,
            'program' => 'AICS',
            'patient_name' => 'DELA CRUZ, JUAN S',
            'date_applied' => '2026-08-01',
            'type' => 'OCA',
            'status' => 'PENDING PAYOUT',
        ]);

        $this->logInAs($this->clientUser());

        $this->post(route('duplicates.destroy'), ['delete_ids' => [$withTx->id, $clean->id]])
            ->assertSessionHas('success', '1 record(s) deleted. 1 skipped (has transactions).');

        $this->assertNotNull(Client::query()->find($withTx->id));
        $this->assertNull(Client::query()->find($clean->id));
    }
}
