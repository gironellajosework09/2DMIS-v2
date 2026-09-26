<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientQrTokenTest extends TestCase
{
    use RefreshDatabase;

    // The real existing-row backfill (1,002 rows on the local main_system) ran
    // inside the additive migration and is verified forensically against a
    // pre-migration dump (byte-identical on every pre-existing column, 0 NULL,
    // 0 duplicates, distinct == row count). By the time this suite refreshes
    // the test database the column is already NOT NULL + UNIQUE, so the
    // pre-migration "rows without tokens" state is unreproducible here; these
    // tests pin the post-migration invariants instead (every row carries a
    // unique valid token, backfill re-runs are no-ops, tokens are immutable).

    private const TOKEN_PATTERN = '/^[0-9A-Za-z]{16}$/';

    private function makeClient(array $overrides = []): Client
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN', 'code' => 'VIG']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'BARANGAY I']);

        return Client::query()->create(array_merge([
            'lastname' => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'middlename' => 'R',
            'extensionname' => null,
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

    public function test_newly_created_client_automatically_receives_qr_token(): void
    {
        $client = $this->makeClient();

        $this->assertNotEmpty($client->qr_token);
        $this->assertMatchesRegularExpression(self::TOKEN_PATTERN, $client->qr_token);
    }

    public function test_generated_tokens_use_the_approved_alphabet_and_length(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $this->assertMatchesRegularExpression(self::TOKEN_PATTERN, Client::generateQrToken());
        }
    }

    public function test_two_clients_never_receive_the_same_token(): void
    {
        $tokens = [];
        foreach (range(1, 5) as $i) {
            $tokens[] = $this->makeClient(['lastname' => "CLIENT$i"])->qr_token;
        }

        $this->assertSame(count($tokens), count(array_unique($tokens)));
    }

    public function test_repeat_backfill_run_never_overwrites_existing_tokens(): void
    {
        $a = $this->makeClient(['lastname' => 'AAA']);
        $b = $this->makeClient(['lastname' => 'BBB']);
        $c = $this->makeClient(['lastname' => 'CCC']);

        $first = array_combine(
            [$a->id, $b->id, $c->id],
            [$a->qr_token, $b->qr_token, $c->qr_token],
        );

        // Rows already carry tokens (as the migration left every existing row);
        // a re-run must assign nothing and change nothing.
        $this->assertSame(0, Client::ensureQrTokens());

        foreach ($first as $id => $token) {
            $this->assertSame($token, DB::table('tbl_clients')->where('id', $id)->value('qr_token'));
        }

        $unique = DB::table('tbl_clients')->distinct()->count('qr_token');
        $this->assertSame(3, $unique);
    }

    public function test_every_existing_client_has_a_unique_valid_token(): void
    {
        collect(range(1, 5))->each(fn ($i) => $this->makeClient(['lastname' => "CLIENT$i"]));

        $total = DB::table('tbl_clients')->count();
        $withToken = DB::table('tbl_clients')->whereNotNull('qr_token')->where('qr_token', '<>', '')->count();
        $distinct = DB::table('tbl_clients')->distinct()->count('qr_token');

        $this->assertSame($total, $withToken);
        $this->assertSame($total, $distinct);
        $this->assertSame(0, DB::table('tbl_clients')->where('qr_token', 'NOT REGEXP', '^[0-9A-Za-z]{16}$')->count());
    }

    public function test_client_creation_preserves_full_name_match_name_and_client_ids(): void
    {
        $client = $this->makeClient();

        $transaction = Transaction::query()->create([
            'client_id' => $client->id,
            'program' => 'CEAP',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-01-10',
            'status' => 'PAID',
            'amount' => 5000,
        ]);

        $token = $client->qr_token;

        $this->assertSame(0, Client::ensureQrTokens());

        $client->refresh();

        $this->assertSame('DELA CRUZ, JUAN R', $client->full_name);
        $this->assertSame('DELACRUZJUANR', $client->match_name);
        $this->assertSame($transaction->client_id, $client->id);
        $this->assertSame($token, $client->qr_token);
    }

    public function test_qr_token_is_not_fillable_and_mass_assignment_cannot_set_it(): void
    {
        $client = $this->makeClient(['qr_token' => 'HACKEDTOKEN1234']);

        $this->assertNotSame('HACKEDTOKEN1234', $client->qr_token);
        $this->assertMatchesRegularExpression(self::TOKEN_PATTERN, $client->qr_token);
        $this->assertFalse((new Client)->isFillable('qr_token'));
    }

    public function test_lastname_edit_does_not_change_qr_token(): void
    {
        $client = $this->makeClient();
        $token = $client->qr_token;

        $client->fill(['lastname' => 'REYES'])->save();

        $this->assertSame($token, $client->fresh()->qr_token);
    }

    public function test_extension_edit_does_not_change_qr_token(): void
    {
        $client = $this->makeClient();
        $token = $client->qr_token;

        $client->fill(['extensionname' => 'JR'])->save();

        $this->assertSame($token, $client->fresh()->qr_token);
    }

    public function test_middle_name_edit_does_not_change_qr_token(): void
    {
        $client = $this->makeClient();
        $token = $client->qr_token;

        $client->fill(['middlename' => 'SANTOS'])->save();

        $this->assertSame($token, $client->fresh()->qr_token);
    }

    public function test_service_update_does_not_change_qr_token(): void
    {
        $client = $this->makeClient();
        $token = $client->qr_token;
        $user = User::factory()->create(['username' => 'clerk']);

        $updated = app(ClientService::class)->update($client, [
            'lastname' => 'REYES',
            'firstname' => 'JUAN',
            'middlename' => 'R',
            'extensionname' => 'JR',
            'birthdate' => '1990-05-15',
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'ip' => 'NO',
            'city_municipality' => $client->city_municipality,
            'barangay' => $client->barangay,
            'house_no' => '',
            'mobile_no' => '09171234567',
            'email' => '',
            'occupation' => '',
            'monthly_income' => '',
            'precinct_no' => '',
            'voter_id' => '',
            'aff_org' => [],
        ], $user->id);

        $this->assertSame($token, $updated->qr_token);
        $this->assertSame('REYES, JUAN R JR', $updated->full_name);
    }
}
