<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 備考は1000文字まで入力できるため、修正申請(attendance_corrections)と同じtext型にする
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE attendances MODIFY comment TEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE attendances MODIFY comment VARCHAR(255) NULL');
    }
};
