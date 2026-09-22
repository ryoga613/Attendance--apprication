<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\ProposalBreak;
use Illuminate\Database\Seeder;

class ProposalBreakSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attendances = Attendance::all();
        foreach ($attendances as $attendance) {
            ProposalBreak::create([
                'attendance_id' => $attendance->id,
                'break_start_at' => $attendance->work_date->copy()->setTime(12, 0, 0),
                'break_end_at' => $attendance->work_date->copy()->setTime(13, 0, 0),
            ]);
        }
    }
}
