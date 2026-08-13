<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrViewerTest extends TestCase
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

    private function transaction(Client $client, array $overrides = []): Transaction
    {
        return Transaction::query()->create(array_merge([
            'client_id' => $client->id,
            'program' => 'CEAP',
            'patient_name' => 'DELA CRUZ, JUAN R',
            'date_applied' => '2026-01-10',
            'status' => 'PAID',
            'amount' => 5000,
            'semester' => '1ST SEMESTER',
            'school_year' => '2025 - 2026',
        ], $overrides));
    }

    public function test_qr_viewer_page_renders_publicly(): void
    {
        $this->get(route('qr-viewer'))
            ->assertOk()
            ->assertSee('Scholar QR Code Viewer')
            ->assertSee('Verify &amp; Load My QR Code', false);
    }

    public function test_qr_search_uses_full_six_program_grantee_search(): void
    {
        $client = $this->client();
        $this->transaction($client, ['program' => 'CEDSSG']);

        $this->get(route('grantee-search', ['kind' => 'grantee']).'?q=DELA')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('results.0.id', $client->id);

        $this->get(route('grantee-search', ['kind' => 'unpaid']).'?q=DELA')
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_qr_verify_returns_persisted_full_name(): void
    {
        $client = $this->client();
        $this->transaction($client, ['program' => 'OTEA']);

        $this->post(route('grantee-search.verify', ['kind' => 'grantee']), [
            'action' => 'verify',
            'client_id' => $client->id,
            'municipality_id' => $client->city_municipality,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('client.full_name', 'DELA CRUZ, JUAN R');
    }
}
