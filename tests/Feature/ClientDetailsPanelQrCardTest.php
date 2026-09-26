<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Client;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * C3-F — Client Details Panel QR Identity Card.
 *
 * Post-C3-F UI cleanup: the card under Personal Information is a compact
 * horizontal presentation — a 120px QR beside explanatory text ("Client QR
 * Code" / "For easy scan access"). Print/Download controls were removed.
 *   * the QR card renders in the panel body (immediately under Personal
 *     Information) with an approximately 120px QR image;
 *   * the QR payload is EXACTLY the persisted `qr_token`
 *     (^[0-9A-Za-z]{16}$) and never a name / display name / client id /
 *     JSON / delimited string;
 *   * the human text is static explanatory UI — the canonical display name
 *     (C3-B) still belongs to the panel header/profile, not the card;
 *   * the token is read-only: editing name parts never regenerates it and
 *     the rendered card always encodes the persisted token;
 *   * neither Print nor Download controls exist in the card;
 *   * the existing 2x2 action grid stays intact.
 */
class ClientDetailsPanelQrCardTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_PATTERN = '/^[0-9A-Za-z]{16}$/';

    private const QR_API = 'https://api.qrserver.com/v1/create-qr-code/';

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

    /** Super-admin user: sees every panel action (grid parity gate). */
    private function superUser(): User
    {
        $user = User::factory()->create(['username' => 'super']);

        return tap($user, fn (User $u) => Permission::query()->create([
            'user_id' => $u->id,
            'page_name' => '*',
            'can_access' => true,
        ]));
    }

    /**
     * @return array{0: Municipality, 1: Barangay}
     */
    private function place(): array
    {
        $municipality = Municipality::query()->create(['name' => 'VIGAN', 'code' => 'VIG']);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'BARANGAY I',
        ]);

        return [$municipality, $barangay];
    }

    private function makeClient(array $overrides = []): Client
    {
        [$municipality, $barangay] = $this->place();

        $base = [
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
        ];

        return Client::query()->create(array_merge($base, $overrides));
    }

    private function openPanel(Client $client): TestResponse
    {
        return $this->get(route('clients.show', $client).'?panel=1')->assertOk();
    }

    /** Extract the qrserver `data=` payload from the rendered panel QR img. */
    private function qrPayload(string $html): string
    {
        $this->assertDoesNotMatchRegularExpression('/data=.*&data=/', $html);

        $this->assertSame(1, preg_match('/id="clientQrImage"\s+src="([^"]+)"/s', $html, $match),
            'Panel HTML must contain the QR img element');

        $this->assertSame(1, preg_match('/[?&]data=([0-9A-Za-z]{16})(?:&|$)/', $match[1], $token),
            'QR img src must carry exactly one data= param holding a 16-char token');

        return $token[1];
    }

    /** The panel header still renders the canonical C3-B display name. */
    private function assertUsesCanonicalDisplayName(string $html, Client $client): void
    {
        $expected = (new ClientService)->deriveDisplayName(
            $client->lastname,
            $client->firstname,
            $client->middlename,
            $client->extensionname,
        );
        $this->assertSame($client->displayFullName(), $expected);
        $this->assertStringContainsString('data-panel-title', $html);
        $this->assertStringContainsString(">$expected<", $html);
    }

    public function test_panel_renders_the_qr_identity_card_under_personal_information(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $html = $this->openPanel($client)->getContent();

        $this->assertStringContainsString('QR Identity Card', $html);
        $this->assertStringContainsString('id="clientQrImage"', $html);

        // Explanatory text of the new horizontal card.
        $this->assertStringContainsString('id="clientQrLabel"', $html);
        $this->assertStringContainsString('>Client QR Code<', $html);
        $this->assertStringContainsString('id="clientQrHint"', $html);
        $this->assertStringContainsString('>For easy scan access<', $html);

        // QR sits beside (before) the text — compact horizontal structure.
        $img = strpos($html, 'id="clientQrImage"');
        $label = strpos($html, 'id="clientQrLabel"');
        $this->assertNotFalse($img);
        $this->assertNotFalse($label);
        $this->assertLessThan($label, $img);

        // Insertion point: immediately under Personal Information, before
        // Contact Information & Address.
        $personal = strpos($html, 'Personal Information');
        $qrCard = strpos($html, 'QR Identity Card');
        $contact = strpos($html, 'Contact Information');

        $this->assertNotFalse($personal);
        $this->assertNotFalse($qrCard);
        $this->assertNotFalse($contact);
        $this->assertLessThan($qrCard, $personal);
        $this->assertLessThan($contact, $qrCard);
    }

    public function test_qr_payload_equals_the_persisted_qr_token(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $html = $this->openPanel($client)->getContent();

        $this->assertSame($client->qr_token, $this->qrPayload($html));
    }

    public function test_qr_payload_is_exactly_16_alphanumeric_characters(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $payload = $this->qrPayload($this->openPanel($client)->getContent());

        $this->assertMatchesRegularExpression(self::TOKEN_PATTERN, $payload);
        $this->assertSame(16, strlen($payload));
    }

    public function test_qr_payload_is_not_full_name(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient(['full_name' => 'DELA CRUZ, JUAN R']);

        $payload = $this->qrPayload($this->openPanel($client)->getContent());

        $this->assertNotSame($client->full_name, $payload);
    }

    public function test_qr_payload_is_not_display_full_name(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $payload = $this->qrPayload($this->openPanel($client)->getContent());

        $this->assertNotSame($client->displayFullName(), $payload);
    }

    public function test_qr_payload_is_not_the_client_id(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $payload = $this->qrPayload($this->openPanel($client)->getContent());

        $this->assertNotSame((string) $client->id, $payload);
    }

    public function test_display_name_comes_from_the_c3_b_formatter(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $html = $this->openPanel($client)->getContent();

        $this->assertUsesCanonicalDisplayName($html, $client);
    }

    public function test_extension_client_displays_last_first_extension_middle(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient([
            'lastname' => 'TESTCLIENT 0014',
            'firstname' => 'MARIA',
            'middlename' => 'L',
            'extensionname' => 'JR',
            'full_name' => 'TESTCLIENT 0014, MARIA L JR',
            'match_name' => 'TESTCLIENT0014MARIALJR',
        ]);

        $html = $this->openPanel($client)->getContent();

        $this->assertUsesCanonicalDisplayName($html, $client);
        $this->assertStringContainsString('TESTCLIENT 0014, MARIA (JR) L', $html);
        $this->assertStringNotContainsString('MARIA L JR', $html);
    }

    public function test_qr_token_remains_unchanged_and_the_card_reflects_it(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $html = $this->openPanel($client)->getContent();
        $first = $this->qrPayload($html);

        // Name edit must not regenerate/alter the token (immutable identity).
        $client->lastname = 'DELA CRUZ UPDATED';
        $client->save();

        $this->assertSame($client->qr_token, $client->fresh()->qr_token);
        $this->assertSame($first, $client->fresh()->qr_token);

        // Re-open the panel: the card still encodes the SAME persisted token.
        $second = $this->qrPayload($this->openPanel($client->fresh())->getContent());
        $this->assertSame($first, $second);
        $this->assertSame($client->fresh()->qr_token, $second);
    }

    public function test_existing_panel_actions_remain_intact(): void
    {
        // All four actions are ACL-gated; use a super-admin so the grid is
        // fully visible (Edit/Delete need action rows, Add Transaction needs
        // all_transactions.php).
        $this->logInAs($this->superUser());
        $client = $this->makeClient();

        $html = $this->openPanel($client)->getContent();

        $this->assertStringContainsString('+ Add Transaction', $html);
        $this->assertStringContainsString('Open Full Page', $html);
        $this->assertStringContainsString('>Edit<', $html);
        $this->assertStringContainsString('Delete', $html);

        // QR card is additive: it must not sit inside the action grid.
        $actions = strpos($html, 'details-actions-line');
        $qrCard = strpos($html, 'QR Identity Card');
        $this->assertNotFalse($actions);
        // Card renders before the (separate) action grid in the partial.
        $this->assertLessThan($actions, $qrCard);
    }

    public function test_print_and_download_controls_are_absent(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $html = $this->openPanel($client)->getContent();

        $this->assertStringNotContainsString('clientQrPrintBtn', $html);
        $this->assertStringNotContainsString('>Print<', $html);
        $this->assertStringNotContainsString('win.print', $html);
        $this->assertStringNotContainsString('clientQrDownloadLink', $html);
        $this->assertStringNotContainsString('>Download QR<', $html);
        $this->assertStringNotContainsString('download=', $html);
    }

    public function test_gironella_duplicate_pair_stays_distinguishable_by_ids_and_tokens(): void
    {
        $this->logInAs($this->clientUser());

        $withMiddle = $this->makeClient([
            'lastname' => 'GIRONELLA',
            'firstname' => 'JOSE',
            'middlename' => 'SOLIVIO',
            'extensionname' => null,
            'full_name' => 'GIRONELLA, JOSE SOLIVIO',
            'match_name' => 'GIRONELLAJOSESOLIVIO',
            'mobile_no' => '09171111111',
        ]);
        $noMiddle = $this->makeClient([
            'lastname' => 'GIRONELLA',
            'firstname' => 'JOSE',
            'middlename' => null,
            'extensionname' => null,
            'full_name' => 'GIRONELLA, JOSE',
            'match_name' => 'GIRONELLAJO',
            'mobile_no' => '09172222222',
        ]);

        $this->assertNotSame($withMiddle->id, $noMiddle->id);
        $this->assertNotSame($withMiddle->qr_token, $noMiddle->qr_token);

        $firstHtml = $this->openPanel($withMiddle)->getContent();
        $secondHtml = $this->openPanel($noMiddle)->getContent();

        $this->assertSame($withMiddle->qr_token, $this->qrPayload($firstHtml));
        $this->assertSame($noMiddle->qr_token, $this->qrPayload($secondHtml));
        $this->assertNotSame($this->qrPayload($firstHtml), $this->qrPayload($secondHtml));

        $this->assertStringContainsString('GIRONELLA, JOSE SOLIVIO', $firstHtml);
        $this->assertStringContainsString('GIRONELLA, JOSE', $secondHtml);
    }

    public function test_qr_card_uses_responsive_friendly_markup(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $html = $this->openPanel($client)->getContent();

        // The card reuses the panel design system (data-card, details-section,
        // ring/radius), is a horizontal flex (QR beside text), and the QR is
        // fixed at ~120px so it never grows to the panel width.
        $this->assertStringContainsString('class="data-card p-[1.25rem]"', $html);
        $this->assertStringContainsString('details-section', $html);
        $this->assertStringContainsString('w-[120px]', $html);
        $this->assertStringContainsString('h-[120px]', $html);
        $this->assertStringContainsString('shrink-0', $html);
        $this->assertStringContainsString('flex flex-wrap items-center', $html);
        $this->assertStringContainsString('rounded-panel', $html);
        $this->assertStringContainsString('ring-1 ring-line', $html);
        $this->assertStringContainsString('width="120"', $html);
        $this->assertStringContainsString('height="120"', $html);
    }

    public function test_qr_card_uses_the_approved_qr_generator_conventions(): void
    {
        $this->logInAs($this->clientUser());
        $client = $this->makeClient();

        $html = $this->openPanel($client)->getContent();

        // The qrserver endpoint and payload stayed byte-identical to C3-F;
        // only the rendered visual size changed (120px CSS/attributes).
        $this->assertStringContainsString(self::QR_API, $html);
        $this->assertStringContainsString('size=220x220', $html);
        $this->assertStringContainsString('format=png', $html);
    }
}
