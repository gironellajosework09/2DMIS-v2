<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the persistent client QR identity token (C3-C).
     *
     * qr_token is a long-lived, reusable, non-expiring identity token:
     * random base62 [0-9A-Za-z] CHAR(16), opaque, unique, independent of the
     * client name/ID/program/transaction. It is NOT an appointment ticket and
     * carries no CEAP semantics.
     *
     * Column collation: the table uses utf8mb4_unicode_ci (case-insensitive),
     * which would treat 'a' and 'A' as equal and break case-distinct
     * uniqueness. The token column overrides collation to utf8mb4_bin so
     * uniqueness semantics are deterministic and case-sensitive.
     *
     * Staged safely: nullable column -> backfill every existing row ->
     * unique index -> NOT NULL. Only NULL/blank rows are ever written, so the
     * backfill is idempotent and never overwrites an existing token.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tbl_clients')) {
            return;
        }

        if (! Schema::hasColumn('tbl_clients', 'qr_token')) {
            Schema::table('tbl_clients', function (Blueprint $table) {
                $table->char('qr_token', 16)
                    ->charset('utf8mb4')
                    ->collation('utf8mb4_bin')
                    ->nullable()
                    ->after('full_name');
            });
        }

        Client::ensureQrTokens();

        if (! Schema::hasIndex('tbl_clients', 'tbl_clients_qr_token_unique')) {
            Schema::table('tbl_clients', function (Blueprint $table) {
                $table->unique('qr_token');
            });
        }

        Schema::table('tbl_clients', function (Blueprint $table) {
            $table->char('qr_token', 16)
                ->charset('utf8mb4')
                ->collation('utf8mb4_bin')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tbl_clients')) {
            return;
        }

        if (Schema::hasIndex('tbl_clients', 'tbl_clients_qr_token_unique')) {
            Schema::table('tbl_clients', function (Blueprint $table) {
                $table->dropUnique('tbl_clients_qr_token_unique');
            });
        }

        if (Schema::hasColumn('tbl_clients', 'qr_token')) {
            Schema::table('tbl_clients', function (Blueprint $table) {
                $table->dropColumn('qr_token');
            });
        }
    }
};
