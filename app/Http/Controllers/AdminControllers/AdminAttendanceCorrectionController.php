<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;

class AdminAttendanceCorrectionController extends Controller
{
    public function approveStampCorrectionRequest($id)
    {
        $user = Auth::user();
        $application = AttendanceCorrection::with('proposalBreaks')->findOrFail($id);

        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    public function updateStampCorrectionRequest($id)
    {
        $application = AttendanceCorrection::findOrFail($id);
        // $application->approval_status = '承認済み';
        // $application->save();

        $attendance = Attendance::findOrFail($application->attendance_id);

        $attendance->update([
            'clock_in_at' => $application->clock_in_at,
            'clock_out_at' => $application->clock_out_at,
        ]);

        $proposalBreaks = $application->proposalBreaks;

        foreach ($proposalBreaks as $proposalBreak) {
            $attendance->breaks()->updateOrCreate(
                ['id' => $proposalBreak->id],
                [
                    'break_start_at' => $proposalBreak->break_start_at,
                    'break_end_at' => $proposalBreak->break_end_at,
                ]
            );
        }

        $application->update(['approval_status' => '承認済み']);

        return redirect()
            ->route('admin.stamp_correction_request.approve', [
                'attendance_correct_request_id' => $id,
            ])->with('success', '勤怠修正申請を承認しました');
    }
}
