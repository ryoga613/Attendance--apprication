<?php

namespace App\Http\Controllers\AdminControllers;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\ProposalBreakCorrection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminAttendanceCorrectionController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', AttendanceCorrection::class);

        $applications = AttendanceCorrection::with(['user', 'AttendanceRecord'])
            ->latest()
            ->get();
        $formattedApplications = $applications->map(function ($application) {
            return [
                'id' => $application->id,
                'date' => $application->AttendanceRecord->work_date,
                'application_date' => $application->created_at,
                'clock_in_at' => $application->clock_in_at,
                'clock_out_at' => $application->clock_out_at,
                'approval_status' => $application->approval_status->value,
                'comment' => $application->comment,
            ];
        });

        $user = Auth::user();

        return view('admin.admin-application-list', compact('applications', 'formattedApplications', 'user'));
    }

    public function store(UpdateAttendanceRequest $request, $id)
    {

        $attendance = Attendance::with('breaks')->findOrFail($id);
        $workDate = $attendance->work_date->format('Y-m-d');

        Gate::authorize('update', $attendance);

        DB::transaction(function () use ($request, $attendance, $workDate) {
            $clockIn = $workDate.' '.$request->new_clock_in;
            $clockOut = $workDate.' '.$request->new_clock_out;

            $attendance->update([
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
            ]);

            $attendanceCorrection = AttendanceCorrection::create([
                'user_id' => $attendance->user_id,
                'attendance_id' => $attendance->id,
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
                'approval_status' => ApprovalStatus::APPROVED,
                'comment' => $request->comment,
            ]);

            $ins = $request->new_break_in ?? [];
            $outs = $request->new_break_out ?? [];
            $breaks = $attendance->breaks()->orderBy('id')->get();

            foreach ($ins as $i => $in) {
                $out = $outs[$i] ?? null;

                if (blank($in) && blank($out)) {
                    if (isset($breaks[$i])) {
                        $breaks[$i]->delete();
                    }

                    continue;
                }

                if (blank($in) || blank($out)) {
                    continue;
                }

                $data = [
                    'break_start_at' => $workDate.' '.$in,
                    'break_end_at' => $workDate.' '.$out,
                ];

                $break = isset($breaks[$i])
                    ? tap($breaks[$i])->update($data)
                    : $attendance->breaks()->create($data);

                ProposalBreakCorrection::create([
                    'attendance_correction_id' => $attendanceCorrection->id,
                    'proposal_break_id' => $break->id,
                    'break_start_at' => $data['break_start_at'],
                    'break_end_at' => $data['break_end_at'],
                ]);
            }
        });

        return redirect()->route('admin.attendance.list')->with('success', '勤怠の修正をしました');
    }

    public function approveStampCorrectionRequest($id)
    {

        // $data = Attendance::with('breaks')->findOrFail($id);
        $application = AttendanceCorrection::with('proposalBreaks')->findOrFail($id);
        // dd($application);

        $breaks = $application->proposalBreaks->map(function ($break) {
            return [
                'break_in' => $break->break_start_at,
                'break_out' => $break->break_end_at,
            ];
        });

        $data = ([

            'breaks' => $breaks,
            'id' => $application->id,
            'date' => $application->clock_in_at->locale('ja')->translatedFormat('n/j(D)'),
            'year' => $application->clock_in_at->format('Y 年'),
            'clock_in' => $application->clock_in_at->format('H:i'),
            'clock_out' => $application->clock_out_at->format('H:i'),
            'comment' => $application->comment,
            'application' => $application,
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();

        Gate::forUser($user)->authorize('view', $application);

        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    public function updateStampCorrectionRequest($id)
    {
        $application = AttendanceCorrection::with('proposalBreaks')->findOrFail($id);
        Gate::authorize('approve', $application);

        if ($application->approval_status === ApprovalStatus::APPROVED) {
            return redirect()
                ->route('stamp_correction_request.approve', ['attendance_correct_request_id' => $id])
                ->withErrors(['approval_status' => 'この申請はすでに承認済みです']);
        }

        DB::transaction(function () use ($application) {
            $attendance = Attendance::findOrFail($application->attendance_id);

            $attendance->update([
                'clock_in_at' => $application->clock_in_at,
                'clock_out_at' => $application->clock_out_at,
            ]);

            // 申請に含まれない休憩は、申請で削除されたものとして消す
            $keepBreakIds = $application->proposalBreaks->pluck('proposal_break_id')->filter();
            $attendance->breaks()->whereNotIn('id', $keepBreakIds)->delete();

            foreach ($application->proposalBreaks as $proposalBreak) {
                $data = [
                    'break_start_at' => $proposalBreak->break_start_at,
                    'break_end_at' => $proposalBreak->break_end_at,
                ];

                // 既存の休憩の修正なら更新、新しく追加された休憩なら作成
                $break = $proposalBreak->proposal_break_id
                    ? $attendance->breaks()->find($proposalBreak->proposal_break_id)
                    : null;

                $break ? $break->update($data) : $attendance->breaks()->create($data);
            }

            $application->update(['approval_status' => ApprovalStatus::APPROVED]);
        });

        return redirect()
            ->route('admin.stamp_correction_request.approve', [
                'attendance_correct_request_id' => $id,
            ])->with('success', '勤怠修正申請を承認しました');
    }
}
