<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Port of v1's log_action() writing to tbl_audit_logs with the same contract.
 */
class AuditService
{
    public function log(
        ?int $userId,
        string $action,
        string $targetTable,
        ?int $targetId = null,
        ?string $oldValue = null,
        ?string $newValue = null,
    ): void {
        DB::table('tbl_audit_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'created_at' => now(),
        ]);
    }
}
