<?php

namespace App\Services;

use App\Models\UnpaidVerification;

/**
 * Unpaid verification write path (P5).
 *
 * Ports v1's unpaid_save.php (duplicate-guarded insert) and the admin delete
 * from fetch_unpaid_verifications.php. v1 wrote NO audit rows for either
 * operation (verified against the source — there are no *_PAYOUT/UNPAID_*
 * audit actions in the v1 codebase), so parity is preserved by not calling
 * AuditService, exactly like the P4 payout attendance write side.
 */
class UnpaidService
{
    /**
     * @return array{success: bool, message?: string, verification?: UnpaidVerification}
     */
    public function create(array $data): array
    {
        $alreadySubmitted = UnpaidVerification::query()
            ->where('client_id', $data['client_id'])
            ->exists();

        if ($alreadySubmitted) {
            return [
                'success' => false,
                'message' => 'You have already submitted your confirmation. Multiple submissions are not allowed.',
            ];
        }

        return [
            'success' => true,
            'verification' => UnpaidVerification::query()->create($data),
        ];
    }

    public function destroy(int $id): bool
    {
        return (bool) UnpaidVerification::query()->whereKey($id)->delete();
    }
}
