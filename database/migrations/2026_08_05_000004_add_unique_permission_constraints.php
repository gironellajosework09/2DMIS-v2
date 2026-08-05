<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce at most one permission row per user/page (and user/program).
     *
     * The UNIQUE constraints are only added when the current data is already
     * consistent. A constraint ALTER can only refuse on bad data — it can never
     * corrupt it — so we skip with a warning if duplicates exist and let an
     * operator resolve the data first.
     */
    public function up(): void
    {
        $this->addUniqueIfClean('tbl_permissions', 'user_id', 'page_name', 'uniq_permission_user_page');
        $this->addUniqueIfClean('tbl_program_permissions', 'user_id', 'program_name', 'uniq_program_permission_user_program');
    }

    private function addUniqueIfClean(string $table, string $first, string $second, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $indexName)) {
            return;
        }

        $duplicates = DB::selectOne(
            "SELECT COUNT(*) AS n FROM (SELECT 1 FROM `$table` GROUP BY `$first`, `$second` HAVING COUNT(*) > 1) d"
        );

        if ((int) $duplicates->n > 0) {
            $this->command->warn(
                "Skipping unique index `$indexName` on `$table`: $duplicates->n duplicate ($first, $second) "
                .'groups found. Deduplicate the data before applying this migration to a copy of production.'
            );

            return;
        }

        Schema::table($table, function (Blueprint $table) use ($first, $second, $indexName) {
            $table->unique([$first, $second], $indexName);
        });
    }

    public function down(): void
    {
        foreach ([
            'tbl_permissions' => 'uniq_permission_user_page',
            'tbl_program_permissions' => 'uniq_program_permission_user_program',
        ] as $table => $indexName) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }
};
