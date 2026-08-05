<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop duplicate / near-duplicate indexes left over from the v1 codebase.
     *
     * Each index below has an equivalent, better-named index covering the same
     * columns, so dropping it is a no-op for query performance:
     *
     *   - tbl_household.household_id_2        -> duplicate of UNIQUE household_id
     *   - tbl_clients.idx_full_name_clients   -> duplicate of idx_fullname (full_name)
     *   - tbl_transactions.t_prg/t_cid/t_da/t_pd/t_dp
     *                                          -> duplicates of idx_program/idx_client_id/
     *                                             idx_date_applied/idx_payout_date/idx_date_paid
     *   - tbl_payout_scans / tbl_payout_scans2 ps_tid/ps_sb/ps_sa
     *                                          -> duplicates of idx_transaction_id/
     *                                             idx_scanned_by/idx_scanned_at
     *   - tbl_payout_scans / tbl_payout_scans2 idx_transaction_id
     *                                          -> redundant: unique_scan (transaction_id) already covers it
     *   - tbl_users.u_un                      -> duplicate of UNIQUE username
     *
     * All drops are existence-guarded so the migration is a safe no-op when run
     * against a fresh database built from a baseline that already has the fixes.
     */
    public function up(): void
    {
        $indexes = [
            'tbl_household' => ['household_id_2'],
            'tbl_clients' => ['idx_full_name_clients'],
            'tbl_transactions' => ['t_prg', 't_cid', 't_da', 't_pd', 't_dp'],
            'tbl_payout_scans' => ['idx_transaction_id', 'ps_tid', 'ps_sb', 'ps_sa'],
            'tbl_payout_scans2' => ['idx_transaction_id', 'ps_tid', 'ps_sb', 'ps_sa'],
            'tbl_users' => ['u_un'],
        ];

        foreach ($indexes as $table => $names) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($names as $name) {
                if (Schema::hasIndex($table, $name)) {
                    DB::statement("ALTER TABLE `$table` DROP INDEX `$name`");
                }
            }
        }
    }

    public function down(): void
    {
        // Restore the redundant indexes exactly as they existed in v1.
        $statements = [
            'ALTER TABLE `tbl_household` ADD UNIQUE `household_id_2` (`household_id`)',
            'ALTER TABLE `tbl_clients` ADD KEY `idx_full_name_clients` (`full_name`)',
            'ALTER TABLE `tbl_transactions` ADD KEY `t_prg` (`program`)',
            'ALTER TABLE `tbl_transactions` ADD KEY `t_cid` (`client_id`)',
            'ALTER TABLE `tbl_transactions` ADD KEY `t_da` (`date_applied`)',
            'ALTER TABLE `tbl_transactions` ADD KEY `t_pd` (`payout_date`)',
            'ALTER TABLE `tbl_transactions` ADD KEY `t_dp` (`date_paid`)',
            'ALTER TABLE `tbl_payout_scans` ADD KEY `idx_transaction_id` (`transaction_id`)',
            'ALTER TABLE `tbl_payout_scans` ADD KEY `ps_tid` (`transaction_id`)',
            'ALTER TABLE `tbl_payout_scans` ADD KEY `ps_sb` (`scanned_by`)',
            'ALTER TABLE `tbl_payout_scans` ADD KEY `ps_sa` (`scanned_at`)',
            'ALTER TABLE `tbl_payout_scans2` ADD KEY `idx_transaction_id` (`transaction_id`)',
            'ALTER TABLE `tbl_payout_scans2` ADD KEY `ps_tid` (`transaction_id`)',
            'ALTER TABLE `tbl_payout_scans2` ADD KEY `ps_sb` (`scanned_by`)',
            'ALTER TABLE `tbl_payout_scans2` ADD KEY `ps_sa` (`scanned_at`)',
            'ALTER TABLE `tbl_users` ADD KEY `u_un` (`username`)',
        ];

        foreach ($statements as $sql) {
            preg_match('/`([^`]+)`/', $sql, $m);
            if (Schema::hasTable($m[1])) {
                DB::statement($sql);
            }
        }
    }
};
