<?php

namespace App\Http\Controllers\UserControllers;

use App\Http\Controllers\AdminControllers\AdminAttendanceController;
use App\Http\Controllers\AdminControllers\AdminAttendanceCorrectionController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\ProposalBreakCorrection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class UserAttendanceCorrectionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $applications = AttendanceCorrection::with(['user', 'AttendanceRecord'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        if (auth()->user()->admin_status === 1) {
            $applications = AttendanceCorrection::with(['user', 'AttendanceRecord'])
                ->latest()
                ->get();
        }

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

        if (auth()->user()->admin_status === 1) {
            return view('admin.admin-application-list', compact('applications', 'formattedApplications', 'user'));
        }

        return view('user.user-application-list', compact('applications', 'formattedApplications', 'user'));
    }

    public function store(AttendanceCorrectionRequest $request, $id)
    {

        $user = Auth::user();
        $attendance = Attendance::findOrFail($id);
        $date = Carbon::parse($attendance->work_date)->toDateString();
        $breaks = $attendance->breaks->sortBy('break_start_at')->values();

        $originalBreaks = $breaks
            ->filter(fn ($b) => $b->break_start_at && $b->break_end_at)
            ->map(fn ($b) => $b->break_start_at->format('H:i').'-'.$b->break_end_at->format('H:i'))
            ->values()
            ->all();

        $newBreaks = collect($request->new_break_in)
            ->map(fn ($in, $i) => [$in, $request->new_break_out[$i] ?? null])
            ->filter(fn ($pair) => $pair[0] && $pair[1])
            ->map(fn ($pair) => Carbon::parse($pair[0])->format('H:i').'-'.Carbon::parse($pair[1])->format('H:i'))
            ->values()
            ->all();

        $noChange = $attendance->clock_in_at?->format('H:i') === Carbon::parse($request->new_clock_in)->format('H:i')
            && $attendance->clock_out_at?->format('H:i') === Carbon::parse($request->new_clock_out)->format('H:i')
            && $originalBreaks === $newBreaks;

        if ($noChange) {
            return redirect()
                ->route('attendance.show', $id)
                ->with('error', '変更がありません');
        }

        $correction = ([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'clock_in_at' => $date.' '.$request->new_clock_in,
            'clock_out_at' => $date.' '.$request->new_clock_out,
            'approval_status' => '承認待ち',
            'comment' => $request->comment,
        ]);

        $correction = AttendanceCorrection::create($correction);

        foreach ($request->new_break_in as $i => $breakIn) {
            $breakOut = $request->new_break_out[$i];

            if (! $breakIn || ! $breakOut) {
                continue;
            }

            $breakRequest = ([
                'attendance_correction_id' => $correction->id,
                'proposal_break_id' => $breaks[$i]->id ?? null,
                'clock_in_at' => $date.' '.$request->new_clock_in,
                'clock_out_at' => $date.' '.$request->new_clock_out,
                'break_start_at' => $date.' '.$breakIn,
                'break_end_at' => $date.' '.$breakOut,
            ]);

            ProposalBreakCorrection::create($breakRequest);
        }

        return redirect()->route('attendance.list')->with('success', '勤怠の修正を申請しました');
    }

    public function stampCorrectionRequestList()
    {
        $user = Auth::user();
        $applications = AttendanceCorrection::with(['user', 'AttendanceRecord'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return view('user.user-application-list', compact('applications'));
    }

    public function approveStampCorrectionRequest($id)
    {
        $user = Auth::user();
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

        if ($user->admin_status === 1) {
            return app(AdminAttendanceCorrectionController::class)->approveStampCorrectionRequest($id);
        }

        return view('user.user-detail', compact('data', 'user'));
    }

    public function detail($id)
    {
        $user = Auth::user();

        if ($user->admin_status) {
            return app(AdminAttendanceController::class)->detail($id);
        }

        $attendance = Attendance::with('breaks')->FindOrFail($id);

        $application = $attendance->AttendanceCorrections->first();

        $breaks = $attendance->breaks->map(function ($break) {
            return [
                'id' => $break->id,
                'break_in' => $break->break_start_at ? Carbon::parse($break->break_start_at)->format('H:i') : null,
                'break_out' => $break->break_end_at ? Carbon::parse($break->break_end_at)->format('H:i') : null,
            ];
        });

        $data = ([
            'application' => $application,
            'id'=> $attendance->id,
            'year'=> $attendance->clock_in_at->format('Y 年'),
            'date' =>$attendance->clock_in_at->locale('ja')->translatedFormat('n/j(D)'),
            'clock_in' => $attendance->clock_in_at->format('H:i'),
            'clock_out' => $attendance->clock_out_at->format('H:i'),
            'breaks'=> $breaks,
            'comment'=> $application->comment,
        ]);

        return view('user.user-detail', compact('user', 'data'));
    }
}
