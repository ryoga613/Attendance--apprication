<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\ProposalBreakCorrection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminAttendanceCorrectionController extends Controller
{
    public function index()
    {
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
                'approval_status' => $application->approval_status,
                'comment' => $application->comment,
            ];
        });

        $user = Auth::user();

        return view('admin.admin-application-list', compact('applications', 'formattedApplications', 'user'));
    }

    // public function store(AttendanceCorrectionRequest $request, $id)
    // {
    //     $user = Auth::guard('admin')->user();

    //     $attendance = Attendance::with('breaks')->findOrFail($id);

    //     $attendance->update([
    //         'work_date' => preg_replace('/\(.+?\)/u', '', $attendance->work_date),
    //         'clock_in_at' => $request->new_clock_in,
    //         'clock_out_at' => $request->new_clock_out,
    //     ]);

    //     $attendanceCorrection = AttendanceCorrection::create([
    //         'user_id' => $user->id,
    //         'attendance_id' => $attendance->id,
    //         'clock_in_at' => $request->new_clock_in,
    //         'clock_out_at' => $request->new_clock_out,
    //         'approval_status' => '承認済み',
    //         'comment' => $request->comment,
    //     ]);

    //     $ins = $request->new_break_in ?? [];
    //     $outs = $request->new_break_out ?? [];

    //     $breaks = $attendance->breaks()->orderBy('id')->get();
    //     $breakCorrection = $attendanceCorrection->proposalBreaks;
    //     // dd($breaks->count(), $ins, $outs);

    //     foreach ($ins as $i => $in) {
    //         $out = $outs[$i] ?? null;

    //         // 空欄の行は何もしない
    //         if (blank($in) || blank($out)) {
    //             continue;
    //         }

    //         $data = [
    //             'break_start_at' => $in,
    //             'break_end_at' => $out,
    //         ];

    //         if (isset($breaks[$i])) {
    //             $break = $breaks[$i];
    //             $break->update($data);
    //         } else {
    //             $break = $attendance->breaks()->create($data);
    //         }
    //         ProposalBreakCorrection::create([
    //             'attendance_correction_id' => $attendanceCorrection->id,
    //             'proposal_break_id' => $break->id,
    //             'break_start_at' => $in ? Carbon::parse($in)->format('Y-m-d H:i:s') : null,
    //             'break_end_at' => $out ? Carbon::parse($out)->format('Y-m-d H:i:s') : null,
    //         ]);
    //     }

    //     return redirect()->route('admin.attendance.list')->with('success', '勤怠の修正を申請しました');
    // }

    public function store(AttendanceCorrectionRequest $request, $id)
    {
        $attendance = Attendance::with('breaks')->findOrFail($id);
        // $breaks = $attendance->breaks
        $workDate = $attendance->work_date->format('Y-m-d');

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
                'approval_status' => '承認済み',
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
        $data = Attendance::with('breaks')->findOrFail($id);
        $application = AttendanceCorrection::with('proposalBreaks')->findOrFail($id);
        $breaks = $data->breaks->map(function ($break) {
            return [
                'break_in' => $break->break_start_at->format('H:i'),
                'break_out' => $break->break_end_at->format('H:i'),
            ];
        });
        $data = ([

            'breaks' => $breaks,
            'id' => $data->id,
            'date' => $data->work_date->locale('ja')->translatedFormat('n/j(D)'),
            'year' => $data->work_date?->format('Y 年'),
            'clock_in' => $data->clock_in_at->format('H:i'),
            'clock_out' => $data->clock_out_at->format('H:i'),
            'comment' => $data->comment,
            'application' => $application,
        ]);

        $user = Auth::guard('admin')->user() ?? Auth::user();
        // dd($application);
        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    public function updateStampCorrectionRequest($id)
    {
        $application = AttendanceCorrection::findOrFail($id);

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
