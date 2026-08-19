<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P12 action-permission pivot (additive, ADR-003 / Open Decision #6.A).
     *
     * Mirrors tbl_program_permissions: presence of a row = allow; no deny rows,
     * no can_access column (P9 §3/§11.C, P12 §2). page_name must be a real v1
     * page key. No FK — consistent with tbl_permissions / tbl_program_permissions.
     */
    public function up(): void
    {
        Schema::create('tbl_action_permissions', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id');
            $table->string('page_name', 100);
            $table->string('action', 50);
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['user_id', 'page_name', 'action'], 'uniq_action_permission_user_page_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_action_permissions');
    }
};
