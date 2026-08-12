<?php

namespace App\Services;

use App\Models\ScholarInfo;

/**
 * V1-parity port of save_scholarship.php.
 *
 * - Upserts the latest (client_id, program) row (ORDER BY id DESC LIMIT 1).
 * - Never writes full_name / match_name / normalized_name — v1 does not.
 * - is_regular defaults to 0 when the field is absent (v1: `isset ? intval : 0`).
 * - year_started is the "YYYY - YYYY" varchar built from year_start / year_end;
 *   one-sided values are allowed and both-empty yields '' (NOT NULL safe).
 * - No UpdateLog is written (v1 has none).
 */
class ScholarService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function save(array $input): ScholarInfo
    {
        $clientId = (int) $input['client_id'];
        $program = trim((string) ($input['program'] ?? ''));

        $isRegular = isset($input['is_regular']) ? (int) $input['is_regular'] : 0;

        $yearStart = trim((string) ($input['year_start'] ?? ''));
        $yearEnd = trim((string) ($input['year_end'] ?? ''));
        if ($yearStart === '' && $yearEnd === '') {
            $yearStarted = '';
        } else {
            $yearStarted = ($yearStart !== '' ? $yearStart : '')
                .($yearStart !== '' || $yearEnd !== '' ? ' - ' : '')
                .($yearEnd !== '' ? $yearEnd : '');
            $yearStarted = trim($yearStarted, ' -');
        }

        $values = [
            'school' => trim((string) ($input['school'] ?? '')),
            'school_type' => trim((string) ($input['school_type'] ?? '')),
            'campus' => trim((string) ($input['campus'] ?? '')),
            'college_department' => trim((string) ($input['college_department'] ?? '')),
            'course' => trim((string) ($input['course'] ?? '')),
            'year_level' => trim((string) ($input['year_level'] ?? '')),
            'is_regular' => $isRegular,
            'landbank_no' => trim((string) ($input['landbank_no'] ?? '')),
            'year_started' => $yearStarted,
        ];

        $existing = ScholarInfo::query()
            ->where('client_id', $clientId)
            ->where('program', $program)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $existing->update($values);

            return $existing;
        }

        return ScholarInfo::create([
            'client_id' => $clientId,
            'program' => $program,
            'full_name' => '',
        ] + $values);
    }
}
