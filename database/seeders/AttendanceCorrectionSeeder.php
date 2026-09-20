<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AttendanceCorrectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        $user2 = User::where('email', 'user2@example.com')->first();
        $user3 = User::where('email', 'user3@example.com')->first();

        $attendance1 = $user1->attendances()->first();
        $attendance2 = $user2->attendances()->first();
        $attendance3 = $user3->attendances()->first();

        // ユーザー1の出勤修正データを作成
        $user1->attendanceCorrections()->create([
            'user_id' => $user1->id,
            'attendance_id' => $attendance1->id,
            'clock_in_at' => $attendance1->work_date->setTime(9, 0, 0),
            'clock_out_at' => $attendance1->work_date->setTime(18, 0, 0),
            'approval_status' => false,
            'comment' => '出勤修正のコメント',
        ]);

        // ユーザー2の出勤修正データを作成
        $user2->attendanceCorrections()->create([
            'user_id' => $user2->id,
            'attendance_id' => $attendance2->id,
            'clock_in_at' => $attendance2->work_date->setTime(9, 0, 0),
            'clock_out_at' => $attendance2->work_date->setTime(18, 0, 0),
            'approval_status' => true,
            'comment' => '出勤修正のコメント',
        ]);

        // ユーザー3の出勤修正データを作成
        $user3->attendanceCorrections()->create([
            'user_id' => $user3->id,
            'attendance_id' => $attendance3->id,
            'clock_in_at' => $attendance3->work_date->setTime(9, 0, 0),
            'clock_out_at' => $attendance3->work_date->setTime(18, 0, 0),
            'approval_status' => false,
            'comment' => '出勤修正のコメント',
        ]);
    }
}
