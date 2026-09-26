<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Services\ClientService;
use Tests\TestCase;

class ClientDisplayNameTest extends TestCase
{
    /**
     * The canonical display name is "LAST, FIRST (EXT) MIDDLE" — extension
     * shown parenthesized BEFORE the middle name (deliberately different from
     * the persisted full_name machine key, which is "LAST, FIRST MIDDLE EXT").
     *
     * The formatter is display-only: it never touches the stored full_name
     * value, the scanner lookup path, or the QR payload.
     */
    public function test_normal_name_without_extension(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN SANTOS',
            (new ClientService)->deriveDisplayName('VIGILIA', 'JUAN', 'SANTOS', null),
        );
    }

    public function test_real_gironella_pair_matches_canonical_shape(): void
    {
        // id 1 / id 1003 real rows: only the middle name separates them.
        $this->assertSame(
            'GIRONELLA, JOSE SOLIVIO',
            (new ClientService)->deriveDisplayName('GIRONELLA', 'JOSE', 'SOLIVIO', null),
        );
    }

    public function test_extension_before_middle_order(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN (JR) SANTOS',
            (new ClientService)->deriveDisplayName('VIGILIA', 'JUAN', 'SANTOS', 'JR'),
        );
    }

    public function test_extension_with_blank_middle_omits_middle_no_trailing_space(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN (JR)',
            (new ClientService)->deriveDisplayName('VIGILIA', 'JUAN', '', 'JR'),
        );
    }

    public function test_blank_extension_omits_parentheses(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN SANTOS',
            (new ClientService)->deriveDisplayName('VIGILIA', 'JUAN', 'SANTOS', ''),
        );
    }

    public function test_null_extension_omits_parentheses(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN SANTOS',
            (new ClientService)->deriveDisplayName('VIGILIA', 'JUAN', 'SANTOS', null),
        );
    }

    public function test_blank_middle_is_omitted(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN',
            (new ClientService)->deriveDisplayName('VIGILIA', 'JUAN', '', null),
        );
    }

    public function test_null_middle_is_omitted(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN',
            (new ClientService)->deriveDisplayName('VIGILIA', 'JUAN', null, null),
        );
    }

    public function test_na_middle_is_omitted(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN',
            (new ClientService)->deriveDisplayName('VIGILIA', 'JUAN', 'N/A', null),
        );
    }

    public function test_leading_and_trailing_whitespace_is_trimmed(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN (JR) SANTOS',
            (new ClientService)->deriveDisplayName('  VIGILIA  ', ' JUAN ', ' SANTOS ', ' JR '),
        );
    }

    public function test_multiple_internal_whitespace_is_collapsed(): void
    {
        $this->assertSame(
            'VIGILIA, JUAN CARLO SANTOS',
            (new ClientService)->deriveDisplayName('VIGILIA', 'JUAN   CARLO', 'SANTOS', null),
        );
    }

    public function test_synthetic_testclient_row(): void
    {
        $this->assertSame(
            'TESTCLIENT 0014, MARIA (JR) L',
            (new ClientService)->deriveDisplayName('TESTCLIENT 0014', 'MARIA', 'L', 'JR'),
        );
    }

    public function test_client_accessor_delegates_to_service(): void
    {
        $client = new Client;
        $client->setAttribute('lastname', 'GIRONELLA');
        $client->setAttribute('firstname', 'JOSE');
        $client->setAttribute('middlename', 'SOLIVIO');
        $client->setAttribute('extensionname', null);

        $this->assertSame(
            (new ClientService)->deriveDisplayName('GIRONELLA', 'JOSE', 'SOLIVIO', null),
            $client->displayFullName(),
        );
        $this->assertSame('GIRONELLA, JOSE SOLIVIO', $client->displayFullName());
    }
}
