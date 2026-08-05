<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add referential integrity to the payout scan tables.
     *
     * `tbl_payout_scans` already declares FKs to tbl_transactions and tbl_users.
     * `tbl_payout_scans2` and `tbl_payout_scans_unpaid` were left without them in
     * v1, so orphaned rows could accumulate silently. The FKs are only added when
     * the current data contains no orphans — otherwise a constraint ALTER would
     * refuse and we warn instead of failing.
     */
    public function up(): void
    {
        $this->addForeignKeys('tbl_payout_scans2');
        $this->addForeignKeys('tbl_payout_scans_unpaid');
    }

    private function addForeignKeys(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable('tbl_transactions') || ! Schema::hasTable('tbl_users')) {
            return;
        }

        $this->addForeignKeyIfNoOrphans($table, 'transaction_id', 'tbl_transactions', 'id', "fk_{$table}_transaction");
        $this->addForeignKeyIfNoOrphans($table, 'scanned_by', 'tbl_users', 'id', "fk_{$table}_user");
    }

    private function addForeignKeyIfNoOrphans(string $table, string $column, string $targetTable, string $targetColumn, string $constraintName): void
    {
        if (collect(Schema::getForeignKeys($table))->contains(fn (array $fk) => $fk['name'] === $constraintName)) {
            return;
        }

        $orphans = DB::selectOne(
            "SELECT COUNT(*) AS n FROM `$table` t LEFT JOIN `$targetTable` x ON x.`$targetColumn` = t.`$column` WHERE x.`$targetColumn` IS NULL"
        );

        if ((int) $orphans->n > 0) {
            $this->command->warn(
                "Skipping foreign key `$constraintName` on `$table.$column`: $orphans->n orphaned rows found. "
                .'Resolve the orphans before applying this migration to a copy of production.'
            );

            return;
        }

        DB::statement(
            "ALTER TABLE `$table` ADD CONSTRAINT `$constraintName` FOREIGN KEY (`$column`) REFERENCES `$targetTable` (`$targetColumn`)"
        );
    }

    public function down(): void
    {
        foreach (['tbl_payout_scans2', 'tbl_payout_scans_unpaid'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (["fk_{$table}_transaction", "fk_{$table}_user"] as $constraintName) {
                if (collect(Schema::getForeignKeys($table))->contains(fn (array $fk) => $fk['name'] === $constraintName)) {
                    DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$constraintName`");
                }
            }
        }
    }
};
