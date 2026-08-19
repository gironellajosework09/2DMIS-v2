<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P12 municipality-scope pivot (additive, Open Decision #6.B/D).
     *
     * municipality_id = 0 is the reserved ALL marker
     * (AccessControlService::ALL_MUNICIPALITY_MARKER); id > 0 is a specific
     * municipality; zero rows = no scope (fail closed). The marker is distinct
     * from tbl_permissions.page_name='*' (P10 §16, P11 §8/§9, P12 §3). No FK —
     * mirrors the permission tables.
     */
    public function up(): void
    {
        Schema::create('tbl_user_municipalities', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('municipality_id');
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['user_id', 'municipality_id'], 'uniq_user_municipality');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_user_municipalities');
    }
};
