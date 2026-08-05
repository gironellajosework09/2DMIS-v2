<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientAffOrg;
use App\Models\FamilyMember;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single write path for clients (ADR-003 heritage / A6 fix): all derived
 * fields (full_name, match_name, age, category) are computed here and nowhere
 * else, matching v1's add/edit behavior (see IMPLEMENTATION_LOG for the
 * match_name consistency deviation).
 */
class ClientService
{
    public const REGION = 'Region I';

    public const PROVINCE = 'Ilocos Sur';

    public function normalizeText(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }

    /**
     * v1 full_name shape: "LASTNAME, FIRSTNAME MIDDLENAME EXTENSION"
     * (middlename omitted when blank or 'N/A').
     */
    public function deriveFullName(
        string $lastname,
        string $firstname,
        ?string $middlename = null,
        ?string $extensionname = null,
    ): string {
        $parts = [$lastname.',', $firstname];

        if (! empty($middlename) && $this->normalizeText($middlename) !== 'N/A') {
            $parts[] = $middlename;
        }

        if (! empty($extensionname)) {
            $parts[] = $extensionname;
        }

        return trim((string) preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    /**
     * Duplicate-matching helper. v1's edit path used the no-space uppercase
     * concatenation; v2 applies it consistently on add as well (A6 fix).
     */
    public function deriveMatchName(
        string $lastname,
        string $firstname,
        ?string $middlename = null,
    ): string {
        return (string) preg_replace(
            '/\s+/',
            '',
            strtoupper(trim($lastname.$firstname.($middlename ?? ''))),
        );
    }

    public function deriveAge(string $birthdate): int
    {
        $birth = DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);

        if ($birth === false) {
            return 0;
        }

        return $birth->diff(new DateTimeImmutable('today'))->y;
    }

    public function deriveCategory(int $age): string
    {
        return match (true) {
            $age <= 17 => 'MINOR (0-17)',
            $age <= 29 => 'YOUTH (18-29)',
            $age <= 59 => 'ADULT (30-59)',
            default => 'SENIOR CITIZEN (60 AND ABOVE)',
        };
    }

    /**
     * Normalize raw form input into the attributes that get persisted.
     * Derived fields (age, category, full_name, match_name) are computed
     * centrally here; the client-supplied age/category are ignored.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function attributes(array $input): array
    {
        $lastname = $this->normalizeText($input['lastname'] ?? null);
        $firstname = $this->normalizeText($input['firstname'] ?? null);
        $middlename = $this->normalizeText($input['middlename'] ?? null);
        $extensionname = $this->normalizeText($input['extensionname'] ?? null);
        $birthdate = (string) ($input['birthdate'] ?? '');
        $age = $this->deriveAge($birthdate);
        $ip = strtoupper(trim((string) ($input['ip'] ?? 'NO')));

        return [
            'lastname' => $lastname,
            'firstname' => $firstname,
            'middlename' => $middlename,
            'extensionname' => $extensionname,
            'region' => self::REGION,
            'province' => self::PROVINCE,
            'city_municipality' => (int) $input['city_municipality'],
            'barangay' => (int) $input['barangay'],
            'house_no' => $this->normalizeText($input['house_no'] ?? null),
            'household_id' => ! empty($input['household_id']) ? (int) $input['household_id'] : null,
            'mobile_no' => trim((string) ($input['mobile_no'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'birthdate' => $birthdate,
            'age' => $age,
            'sex' => strtoupper(trim((string) ($input['sex'] ?? ''))),
            'civil_status' => strtoupper(trim((string) ($input['civil_status'] ?? ''))),
            'pwd' => strtoupper(trim((string) ($input['pwd'] ?? 'NO'))),
            'ip' => $ip,
            'ip_group' => $ip === 'YES' && ! empty($input['ip_group'])
                ? trim((string) $input['ip_group'])
                : null,
            'occupation' => $this->normalizeText($input['occupation'] ?? null),
            'monthly_income' => ($input['monthly_income'] ?? '') !== ''
                ? round((float) $input['monthly_income'], 2)
                : null,
            'category' => $this->deriveCategory($age),
            'precinct_no' => $this->normalizeText($input['precinct_no'] ?? null),
            'voter_id' => $this->normalizeText($input['voter_id'] ?? null),
            'full_name' => $this->deriveFullName($lastname, $firstname, $middlename, $extensionname),
            'match_name' => $this->deriveMatchName($lastname, $firstname, $middlename),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, int $userId): Client
    {
        $attributes = $this->attributes($input);
        $attributes['aff_org'] = '';

        return DB::transaction(function () use ($attributes, $input, $userId) {
            /** @var Client $client */
            $client = Client::create($attributes);

            $orgs = $this->syncAffiliations($client->id, $input['aff_org'] ?? []);

            $payload = $attributes;
            $payload['aff_orgs'] = $orgs;

            app(AuditService::class)->log(
                $userId,
                'ADD_CLIENT',
                'tbl_clients',
                $client->id,
                null,
                json_encode($payload),
            );

            return $client->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Client $client, array $input, int $userId): Client
    {
        $attributes = $this->attributes($input);
        $old = $client->only(array_keys($attributes));

        DB::transaction(function () use ($client, $attributes, $input) {
            $client->fill($attributes)->save();
            $this->syncAffiliations($client->id, $input['aff_org'] ?? []);
        });

        $new = $client->fresh()->only(array_keys($attributes));

        $changes = [];
        foreach ($new as $key => $newValue) {
            if (($old[$key] ?? null) != $newValue) {
                $changes[$key] = ['old' => $old[$key] ?? null, 'new' => $newValue];
            }
        }

        if ($changes !== []) {
            app(AuditService::class)->log(
                $userId,
                'EDIT_CLIENT',
                'tbl_clients',
                $client->id,
                json_encode(array_column($changes, 'old')),
                json_encode(array_column($changes, 'new')),
            );
        }

        return $client->fresh();
    }

    /**
     * Delete a client (port of v1 delete_client.php).
     *
     * Guard (v1 FK behavior): a client that has transactions cannot be
     * deleted — tbl_transactions.client_id has no ON DELETE CASCADE, so v1's
     * raw DELETE failed with a constraint error. v2 surfaces the same guard as
     * an explicit check.
     *
     * Deviation: v1 left orphaned tbl_family_members rows (the table has no
     * FK); v2 removes both-direction family links and relies on the existing
     * ON DELETE CASCADE for tbl_client_photos.
     */
    public function destroy(Client $client, User $user): void
    {
        if ($client->transactions()->exists()) {
            throw new InvalidArgumentException('Cannot delete client with recorded transactions.');
        }

        DB::transaction(function () use ($client, $user) {
            FamilyMember::query()
                ->where('client_id', $client->id)
                ->orWhere('relative_id', $client->id)
                ->delete();

            $old = $client->getAttributes();

            $client->delete();

            app(AuditService::class)->log(
                $user->id,
                'DELETE_CLIENT',
                'tbl_clients',
                $client->id,
                json_encode($old),
                null,
            );
        });
    }

    /**
     * Replace a client's affiliated organizations (v1 delete-then-insert).
     *
     * @param  mixed  $orgs
     * @return list<string>
     */
    private function syncAffiliations(int $clientId, $orgs): array
    {
        ClientAffOrg::where('client_id', $clientId)->delete();

        $normalized = [];
        foreach ((array) $orgs as $org) {
            $org = $this->normalizeText($org);
            if ($org !== '') {
                $normalized[$org] = $org;
            }
        }

        foreach ($normalized as $org) {
            ClientAffOrg::create(['client_id' => $clientId, 'organization' => $org]);
        }

        return array_values($normalized);
    }
}
