<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function logInAs(User $user): void
    {
        $user->session_token = 'token';
        $user->save();

        $this->withSession(['session_token' => 'token'])->actingAs($user);
    }

    private function clientsUser(): User
    {
        $user = User::factory()->create(['username' => 'clerk']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'clients.php',
            'can_access' => true,
        ]);

        return $user;
    }

    private function place(string $name = 'VIGAN'): array
    {
        $municipality = Municipality::query()->create(['name' => $name]);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'BARANGAY I',
        ]);

        return [$municipality, $barangay];
    }

    private function createClient(Municipality $municipality, Barangay $barangay, array $overrides = []): Client
    {
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

    // ── Authentication ──────────────────────────────────────────

    public function test_global_search_requires_authentication(): void
    {
        $this->getJson(route('global-search', ['q' => 'CRUZ']))
            ->assertStatus(401);
    }

    // ── Search endpoint basics ──────────────────────────────────

    public function test_search_returns_matching_clients(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->createClient($municipality, $barangay, [
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'full_name' => 'DELA CRUZ, JUAN R',
            'match_name' => 'DELACRUZJUANR',
        ]);

        $this->logInAs($this->clientsUser());

        $json = $this->getJson(route('global-search', ['q' => 'DELA']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['results']);
        $this->assertSame('DELA CRUZ, JUAN R', $json['results'][0]['full_name']);
        $this->assertSame('VIGAN', $json['results'][0]['municipality']);
        $this->assertSame('BARANGAY I', $json['results'][0]['barangay']);
        $this->assertArrayHasKey('url', $json['results'][0]);
    }

    public function test_search_returns_empty_for_short_query(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->createClient($municipality, $barangay);

        $this->logInAs($this->clientsUser());

        $this->getJson(route('global-search', ['q' => 'D']))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_search_returns_empty_for_empty_query(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->createClient($municipality, $barangay);

        $this->logInAs($this->clientsUser());

        $this->getJson(route('global-search', ['q' => '']))
            ->assertOk()
            ->assertJsonPath('results', []);

        $this->getJson(route('global-search'))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->createClient($municipality, $barangay);

        $this->logInAs($this->clientsUser());

        $this->getJson(route('global-search', ['q' => 'XYZNONEXISTENT']))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    // ── Result limit ────────────────────────────────────────────

    public function test_search_limits_results_to_eight(): void
    {
        [$municipality, $barangay] = $this->place();

        for ($i = 1; $i <= 12; $i++) {
            $this->createClient($municipality, $barangay, [
                'lastname' => 'SMITH',
                'firstname' => sprintf('PERSON%02d', $i),
                'full_name' => sprintf('SMITH, PERSON%02d', $i),
                'match_name' => 'SMITH'.sprintf('PERSON%02d', $i),
            ]);
        }

        $this->logInAs($this->clientsUser());

        $json = $this->getJson(route('global-search', ['q' => 'SMITH']))
            ->assertOk()
            ->json();

        $this->assertCount(8, $json['results']);
    }

    // ── Search semantics ────────────────────────────────────────

    public function test_search_matches_firstname(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->createClient($municipality, $barangay, [
            'firstname' => 'JUANITO',
            'full_name' => 'DELA CRUZ, JUANITO R',
            'match_name' => 'DELACRUZJUANITOR',
        ]);

        $this->logInAs($this->clientsUser());

        $json = $this->getJson(route('global-search', ['q' => 'JUANITO']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['results']);
    }

    public function test_search_matches_municipality_name(): void
    {
        [$municipality, $barangay] = $this->place('CANDON');
        $this->createClient($municipality, $barangay, [
            'full_name' => 'REYES, MARIA',
            'match_name' => 'REYESMARIA',
        ]);

        $this->logInAs($this->clientsUser());

        $json = $this->getJson(route('global-search', ['q' => 'CANDON']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['results']);
        $this->assertSame('CANDON', $json['results'][0]['municipality']);
    }

    public function test_search_word_split_and_behavior(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->createClient($municipality, $barangay, [
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'full_name' => 'DELA CRUZ, JUAN',
            'match_name' => 'DELACRUZJUAN',
        ]);

        $this->logInAs($this->clientsUser());

        // Both words must match (AND) — "DELA JUAN" should match
        $json = $this->getJson(route('global-search', ['q' => 'DELA JUAN']))
            ->assertOk()
            ->json();
        $this->assertCount(1, $json['results']);

        // "DELA XYZ" should not match — XYZ doesn't exist in any field
        $json = $this->getJson(route('global-search', ['q' => 'DELA XYZ']))
            ->assertOk()
            ->json();
        $this->assertCount(0, $json['results']);
    }

    public function test_search_smart_ranking_prefix_first(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->createClient($municipality, $barangay, [
            'firstname' => 'REY',
            'full_name' => 'SANTOS, REY',
            'match_name' => 'SANTOSREY',
        ]);
        $this->createClient($municipality, $barangay, [
            'firstname' => 'REYMART',
            'lastname' => 'DELA CRUZ',
            'full_name' => 'DELA CRUZ, REYMART',
            'match_name' => 'DELACRUZREYMART',
        ]);

        $this->logInAs($this->clientsUser());

        $json = $this->getJson(route('global-search', ['q' => 'REY']))
            ->assertOk()
            ->json();

        $this->assertCount(2, $json['results']);
        // "REY" (exact prefix on firstname) should rank before "REYMART"
        $this->assertSame('SANTOS, REY', $json['results'][0]['full_name']);
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_search_returns_empty_for_unauthorized_user(): void
    {
        [$municipality, $barangay] = $this->place();
        $this->createClient($municipality, $barangay);

        $user = User::factory()->create(['username' => 'noperm']);
        $this->logInAs($user);

        $this->getJson(route('global-search', ['q' => 'DELA']))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    // ── Municipality scope ──────────────────────────────────────

    private function enforce(string $pageName): void
    {
        $pages = config('authorization.pages');
        $pages[$pageName]['enforcement'] = true;
        config(['authorization.pages' => $pages]);
    }

    public function test_search_respects_municipality_scope(): void
    {
        [$muni1, $bgy1] = $this->place('VIGAN');
        [$muni2, $bgy2] = $this->place('CANDON');

        $this->createClient($muni1, $bgy1, [
            'full_name' => 'DELA CRUZ, JUAN',
            'match_name' => 'DELACRUZJUAN',
        ]);
        $this->createClient($muni2, $bgy2, [
            'full_name' => 'DELA CRUZ, PEDRO',
            'match_name' => 'DELACRUZPEDRO',
        ]);

        $user = User::factory()->create(['username' => 'scoped']);
        Permission::query()->create([
            'user_id' => $user->id,
            'page_name' => 'clients.php',
            'can_access' => true,
        ]);
        DB::table('tbl_user_municipalities')->insert([
            'user_id' => $user->id,
            'municipality_id' => $muni1->id,
        ]);
        $this->enforce('clients.php');
        $this->logInAs($user);

        $json = $this->getJson(route('global-search', ['q' => 'DELA']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['results']);
        $this->assertSame('VIGAN', $json['results'][0]['municipality']);
    }

    // ── Client detail navigation ────────────────────────────────

    public function test_search_result_includes_show_url(): void
    {
        [$municipality, $barangay] = $this->place();
        $client = $this->createClient($municipality, $barangay);

        $this->logInAs($this->clientsUser());

        $json = $this->getJson(route('global-search', ['q' => 'DELA']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['results']);
        $this->assertSame(route('clients.show', $client->id), $json['results'][0]['url']);
    }

    // ── Dashboard page includes search partial ──────────────────

    public function test_dashboard_renders_global_search_input(): void
    {
        $user = User::factory()->create();
        $this->logInAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('globalSearch')
            ->assertSee('global-search');
    }
}
