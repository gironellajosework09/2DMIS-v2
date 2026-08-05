<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unify table collations on utf8mb4_unicode_ci.
     *
     * The v1 codebase mixed utf8mb4_general_ci and utf8mb4_unicode_ci across
     * tables, which breaks string comparisons in joins. CONVERT TO CHARACTER SET
     * rewrites character columns in place and is data-preserving. It is also a
     * safe no-op when the baseline already uses utf8mb4_unicode_ci.
     */
    public function up(): void
    {
        foreach (['tbl_barangays', 'tbl_clients', 'tbl_family_members', 'tbl_municipalities', 'tbl_users'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        }
    }

    public function down(): void
    {
        foreach (['tbl_barangays', 'tbl_clients', 'tbl_family_members', 'tbl_municipalities', 'tbl_users'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
            );
        }
    }
};
