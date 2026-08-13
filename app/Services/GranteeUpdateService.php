<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ScholarInfo;
use App\Models\Transaction;
use App\Models\UpdateLog;
use Illuminate\Support\Facades\DB;

/**
 * V1-parity port of save_grantee_update.php — the grantee self-update write
 * path behind the public disabled_update_grantee.php form.
 *
 * In one transaction it:
 *  - updates tbl_clients with the POSTed profile fields (name parts,
 *    municipality/barangay are PRESERVED from the DB row — the form renders
 *    them read-only, so v1 overwrites them with the stored values);
 *  - upserts the latest tbl_scholar_info row for (client_id, program):
 *    UPDATE sets updated_at = NOW(); INSERT writes the comma-form full_name and
 *    created_at = NOW() — v1 does not touch full_name on the client row here;
 *  - appends an UPDATE_LOG row (tbl_update_logs) with the space-joined
 *    "FIRST MIDDLE LAST" full_name, the request IP and the exact action string
 *    'Grantee self-updated their information.' — a module log, never
 *    tbl_audit_logs (SCHOLAR_ANALYSIS §7 two-log distinction).
 *
 * Return shape mirrors the v1 JSON contract ({success, message}).
 */
class GranteeUpdateService
{
    private const ALLOWED_PROGRAMS = ['CEAP', 'CEAP_NEW', 'CEDSSG', 'CEDSSG_NEW', 'OTEA', 'OTCES'];

    private const REQUIRED = ['mobile_no', 'email', 'birthdate', 'sex', 'civil_status'];

    private const PRESERVED = ['lastname', 'firstname', 'middlename', 'extensionname', 'city_municipality', 'barangay'];

    private const UPPERCASED = [
        'lastname', 'firstname', 'middlename', 'extensionname', 'house_no',
        'sex', 'civil_status', 'pwd', 'ip', 'ip_group', 'occupation',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array{success: bool, message?: string}
     */
    public function update(array $input, string $ip): array
    {
        $clientId = (int) ($input['client_id'] ?? 0);

        if ($clientId === 0) {
            return ['success' => false, 'message' => 'Missing client id'];
        }

        $client = Client::query()->find($clientId);

        if ($client === null) {
            return ['success' => false, 'message' => 'Client not found'];
        }

        $program = Transaction::query()
            ->where('client_id', $clientId)
            ->whereIn('program', self::ALLOWED_PROGRAMS)
            ->orderByDesc('id')
            ->value('program');

        if ($program === null) {
            return ['success' => false, 'message' => 'No qualifying scholarship transaction found for this client.'];
        }

        $fields = $this->profileFields($input);

        foreach (self::PRESERVED as $column) {
            $fields[$column] = $client->{$column};
        }

        foreach (self::REQUIRED as $required) {
            if (empty($fields[$required])) {
                return ['success' => false, 'message' => "Field '$required' is required."];
            }
        }

        $schFields = [
            'school' => mb_strtoupper(trim((string) ($input['school'] ?? '')), 'UTF-8'),
            'college_department' => mb_strtoupper(trim((string) ($input['college_department'] ?? '')), 'UTF-8'),
            'course' => mb_strtoupper(trim((string) ($input['course'] ?? '')), 'UTF-8'),
            'year_level' => trim((string) ($input['year_level'] ?? '')),
            'is_regular' => ($input['is_regular'] ?? null) == '1' ? 1 : 0,
        ];

        try {
            DB::transaction(function () use ($client, $clientId, $program, $fields, $schFields, $ip) {
                $client->update($fields);

                $scholar = ScholarInfo::query()
                    ->where('client_id', $clientId)
                    ->where('program', $program)
                    ->orderByDesc('id')
                    ->first();

                if ($scholar) {
                    $scholar->update($schFields + ['updated_at' => now()]);
                } else {
                    ScholarInfo::create([
                        'client_id' => $clientId,
                        'full_name' => trim($fields['lastname'].', '.$fields['firstname'].' '.$fields['middlename']),
                        'program' => $program,
                        'year_started' => '',
                        'landbank_no' => '',
                        'created_at' => now(),
                    ] + $schFields);
                }

                UpdateLog::create([
                    'client_id' => $clientId,
                    'full_name' => trim($fields['firstname'].' '.$fields['middlename'].' '.$fields['lastname']),
                    'ip_address' => $ip === '' ? 'UNKNOWN' : $ip,
                    'action' => 'Grantee self-updated their information.',
                ]);
            });

            return ['success' => true];
        } catch (\Throwable $e) {
            report($e);

            return ['success' => false, 'message' => 'An error occurred while saving.'];
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function profileFields(array $input): array
    {
        $fields = [
            'house_no' => '',
            'mobile_no' => trim((string) ($input['mobile_no'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'birthdate' => (string) ($input['birthdate'] ?? ''),
            'age' => (int) ($input['age'] ?? 0),
            'pwd' => '',
            'ip' => '',
            'ip_group' => '',
            'occupation' => '',
        ];

        foreach (self::UPPERCASED as $column) {
            $fields[$column] = mb_strtoupper(trim((string) ($input[$column] ?? '')), 'UTF-8');
        }

        $fields['city_municipality'] = (int) ($input['city_municipality'] ?? 0);
        $fields['barangay'] = (int) ($input['barangay'] ?? 0);

        foreach (['pwd', 'ip'] as $yesNo) {
            if (! in_array($fields[$yesNo], ['YES', 'NO'], true)) {
                $fields[$yesNo] = 'NO';
            }
        }

        return $fields;
    }
}
