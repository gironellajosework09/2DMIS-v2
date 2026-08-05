<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow `tbl_clients.email` to be NULL.
     *
     * The v1 application did not always collect an email address, but the column
     * was declared NOT NULL with no default, forcing inserts of empty strings.
     * Empty strings are preserved; only the NOT NULL flag is relaxed.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tbl_clients')) {
            return;
        }

        Schema::table('tbl_clients', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tbl_clients')) {
            return;
        }

        $hasNulls = DB::table('tbl_clients')->whereNull('email')->exists();

        if ($hasNulls) {
            $this->command->warn(
                'Not restoring NOT NULL on `tbl_clients.email`: NULL rows exist. Clean the data first.'
            );

            return;
        }

        Schema::table('tbl_clients', function (Blueprint $table) {
            $table->string('email', 255)->change();
        });
    }
};
