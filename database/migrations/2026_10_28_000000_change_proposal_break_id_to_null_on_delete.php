<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 休憩を削除しても、過去の修正申請の休憩は履歴として残す
     */
    public function up(): void
    {
        Schema::table('proposal_break_corrections', function (Blueprint $table) {
            $table->dropForeign(['proposal_break_id']);
            $table->foreign('proposal_break_id')->references('id')->on('proposal_breaks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_break_corrections', function (Blueprint $table) {
            $table->dropForeign(['proposal_break_id']);
            $table->foreign('proposal_break_id')->references('id')->on('proposal_breaks')->cascadeOnDelete();
        });
    }
};
