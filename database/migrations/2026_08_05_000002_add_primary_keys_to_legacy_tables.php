<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every legacy table a primary key.
     *
     * `gender` and `tbl_details` already have an `id` column but it is nullable
     * and unkeyed; the other three tables have no identifier column at all.
     *
     * Adding an auto-increment primary key to a table that already holds rows is
     * data-preserving: MySQL assigns sequential values. For `gender` and
     * `tbl_details` the ALTER is only attempted when the existing `id` values are
     * all present and unique — otherwise a UNIQUE PRIMARY KEY could not be built
     * and we warn instead of failing the migration.
     */
    public function up(): void
    {
        $this->addIdToExisting($this->isExistingIdClean('gender'), 'gender', 'INT(4)');
        $this->addIdToExisting($this->isExistingIdClean('tbl_details'), 'tbl_details', 'INT(4)');

        foreach (['tbl_absent', 'tbl_kababaihan', 'temp_details'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if ($this->hasPrimaryKey($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->id('id')->first();
            });
        }
    }

    private function isExistingIdClean(string $table): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        if ($this->hasPrimaryKey($table)) {
            return true;
        }

        $row = DB::selectOne(
            "SELECT COUNT(*) AS total, COUNT(`id`) AS nonnull, COUNT(DISTINCT `id`) AS distinct_ids FROM `$table`"
        );

        return $row->total === $row->nonnull && $row->nonnull === $row->distinct_ids;
    }

    private function addIdToExisting(bool $safe, string $table, string $type): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if ($this->hasPrimaryKey($table)) {
            return;
        }

        if (! $safe) {
            $this->command->warn(
                "Skipping primary key on `$table`: existing `id` values are missing or duplicated. "
                .'Review the data before applying this migration to a copy of production.'
            );

            return;
        }

        DB::statement("ALTER TABLE `$table` MODIFY `id` $type NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)");
    }

    private function hasPrimaryKey(string $table): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => $index['primary'] ?? false);
    }

    public function down(): void
    {
        foreach (['tbl_absent', 'tbl_kababaihan', 'temp_details'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->dropPrimary();
                $table->dropColumn('id');
            });
        }

        foreach (['gender' => 'INT(4)', 'tbl_details' => 'INT(4)'] as $table => $type) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("ALTER TABLE `$table` MODIFY `id` $type DEFAULT NULL, DROP PRIMARY KEY");
        }
    }
};
