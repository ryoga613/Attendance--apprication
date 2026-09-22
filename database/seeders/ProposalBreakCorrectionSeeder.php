<?php

namespace Database\Seeders;

use App\Models\AttendanceCorrection;
use App\Models\ProposalBreakCorrection;
use Illuminate\Database\Seeder;

class ProposalBreakCorrectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $proposalCorrections = AttendanceCorrection::all();

        foreach ($proposalCorrections as $proposalCorrection) {

            $attendance = $proposalCorrection->attendance;

            $proposalBreak = $attendance->proposalBreaks->first();

            ProposalBreakCorrection::create([
                'attendance_correction_id' => $proposalCorrection->id,
                'proposal_break_id' => $proposalBreak->id,
                'break_start_at' => $proposalCorrection->clock_in_at->copy()->setTime(12, 0, 0),
                'break_end_at' => $proposalCorrection->clock_in_at->copy()->setTime(13, 0, 0),
            ]);
        }
    }
}
