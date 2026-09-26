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

    public function test_qr_verify_returns_qr_token_for_payload(): void
    {
        $client = $this->client();
        $this->transaction($client);

        $this->post(route('grantee-search.verify', ['kind' => 'grantee']), [
            'action' => 'verify',
            'client_id' => $client->id,
            'municipality_id' => $client->city_municipality,
        ])
            ->assertOk()
            ->assertJsonPath('client.qr_token', $client->qr_token);
    }

    public function test_qr_verify_qr_token_is_base62_16chars(): void
    {
        $client = $this->client();
        $this->transaction($client);

        $json = $this->post(route('grantee-search.verify', ['kind' => 'grantee']), [
            'action' => 'verify',
            'client_id' => $client->id,
            'municipality_id' => $client->city_municipality,
        ])->json();

        $this->assertArrayHasKey('qr_token', $json['client']);
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{16}$/', $json['client']['qr_token']);
    }

    public function test_qr_verify_extension_client_payload_is_token_not_display_name(): void
    {
        $client = $this->client([
            'lastname' => 'TESTCLIENT 0014',
            'firstname' => 'MARIA',
            'middlename' => 'L',
            'extensionname' => 'JR',
            'full_name' => 'TESTCLIENT 0014, MARIA L JR',
            'match_name' => 'TESTCLIENT0014MARIALJR',
        ]);
        $this->transaction($client);

        $json = $this->post(route('grantee-search.verify', ['kind' => 'grantee']), [
            'action' => 'verify',
            'client_id' => $client->id,
            'municipality_id' => $client->city_municipality,
        ])->json();

        // C3-E: the QR payload (qr_token) is the opaque identity token, never
        // the canonical display name and never the persisted comma-form name.
        $payload = $json['client']['qr_token'];
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{16}$/', $payload);
        $this->assertSame($client->qr_token, $payload);
        $this->assertNotSame($payload, $client->displayFullName());
        $this->assertNotSame($payload, $client->full_name);
        $this->assertSame('TESTCLIENT 0014, MARIA (JR) L', $client->displayFullName());
    }

    public function test_qr_viewer_page_wires_qr_token_payload_and_not_fullname(): void
    {
        $html = $this->get(route('qr-viewer'))->getContent();

        // C3-E: the page's JS builds the QR data from qr_token, not fullName.
        $this->assertStringContainsString('data.client.qr_token', $html);
        $this->assertStringContainsString('encodeURIComponent(qrPayload)', $html);
        $this->assertStringNotContainsString('encodeURIComponent(fullName)', $html);
    }
}
