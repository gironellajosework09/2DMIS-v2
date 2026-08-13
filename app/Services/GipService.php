<?php

namespace App\Services;

use App\Models\GipInfo;

/**
 * V1-parity port of save_gip.php.
 *
 * - Upserts the latest tbl_gip_info row for a client (ORDER BY id DESC LIMIT 1);
 *   an existing row is updated in place, otherwise a new row is inserted.
 * - Uppercases every profile field via mb_strtoupper except ecp_contact_number
 *   and year_graduated, exactly matching v1's sanitization block.
 * - Writes ADD_GIP / UPDATE_GIP rows to tbl_audit_logs (target_table
 *   'tbl_clients', target_id = client_id), with old/new JSON payloads exactly
 *   like v1's log_action(); an update is only logged when something changed.
 */
class GipService
{
    private const UPPERCASED = [
        'valid_govt_id',
        'id_number',
        'insurance_beneficiary',
        'emergency_contact',
        'ecp_address',
        'college',
        'course',
        'high_school',
        'elementary_school',
        'latest_work_experience',
        'position',
        'period_of_engagement',
        'special_skills',
        'achievements',
    ];

    private const COLUMNS = [
        'valid_govt_id',
        'id_number',
        'insurance_beneficiary',
        'emergency_contact',
        'ecp_contact_number',
        'ecp_address',
        'college',
        'course',
        'year_graduated',
        'high_school',
        'elementary_school',
        'latest_work_experience',
        'position',
        'period_of_engagement',
        'special_skills',
        'achievements',
    ];

    public function __construct(private readonly AuditService $auditService) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(array $input, int $userId): GipInfo
    {
        $clientId = (int) $input['client_id'];

        $values = [];
        foreach (self::COLUMNS as $column) {
            $raw = trim((string) ($input[$column] ?? ''));

            $values[$column] = in_array($column, self::UPPERCASED, true)
                ? mb_strtoupper($raw, 'UTF-8')
                : $raw;
        }

        $existing = GipInfo::query()
            ->where('client_id', $clientId)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $oldJson = json_encode($existing->only(self::COLUMNS));

            $existing->update($values);

            $newJson = json_encode($existing->fresh()->only(self::COLUMNS));

            if ($oldJson !== $newJson) {
                $this->auditService->log(
                    $userId,
                    'UPDATE_GIP',
                    'tbl_clients',
                    $clientId,
                    $oldJson,
                    $newJson,
                );
            }

            return $existing->fresh();
        }

        $gip = GipInfo::create([
            'client_id' => $clientId,
        ] + $values);

        $this->auditService->log(
            $userId,
            'ADD_GIP',
            'tbl_clients',
            $clientId,
            null,
            json_encode($gip->fresh()->only(self::COLUMNS)),
        );

        return $gip->fresh();
    }
}
