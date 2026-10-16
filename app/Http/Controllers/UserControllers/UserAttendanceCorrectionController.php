<?php

namespace App\Http\Controllers\UserControllers;

use App\Http\Controllers\AdminControllers\AdminAttendanceController;
use App\Http\Controllers\AdminControllers\AdminAttendanceCorrectionController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\ProposalBreakCorrection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserAttendanceCorrectionController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', AttendanceCorrection::class);

        if (Auth::guard('admin')->check()) {
            return app(AdminAttendanceCorrectionController::class)->index();
        }

        $user = Auth::user();
        $applications = AttendanceCorrection::with(['user', 'AttendanceRecord'])
            ->where('user_id', $user->id)
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

        return view('user.user-application-list', compact('applications', 'formattedApplications', 'user'));
    }

    public function store(StoreAttendanceCorrectionRequest $request, $id)
    {
        Gate::authorize('store', AttendanceCorrection::class);

        $validated = $request->validated();

        $user = Auth::user();
        $attendance = Attendance::findOrFail($id);

        Gate::authorize('requestCorrection', $attendance);
        $date = Carbon::parse($attendance->work_date)->toDateString();

        // 休憩データの取得とフォーマット（Carbonキャスト前提）
        $breaks = $attendance->breaks->sortBy('break_start_at')->values();

        $originalBreaks = $breaks
            ->filter(fn ($b) => $b->break_start_at && $b->break_end_at)
            ->map(fn ($b) => Carbon::parse($b->break_start_at)->format('H:i').'-'.Carbon::parse($b->break_end_at)->format('H:i'))
            ->values()
            ->all();

        $newBreakIns = $validated['new_break_in'] ?? [];
        $newBreakOuts = $validated['new_break_out'] ?? [];

        $newBreaks = collect($newBreakIns)
            ->map(fn ($in, $i) => [$in, $newBreakOuts[$i] ?? null])
            ->filter(fn ($pair) => ! empty($pair[0]) && ! empty($pair[1]))
            ->map(fn ($pair) => Carbon::parse($pair[0])->format('H:i').'-'.Carbon::parse($pair[1])->format('H:i'))
            ->values()
            ->all();

        // 変更有無の判定
        $noChange = $attendance->clock_in_at?->format('H:i') === Carbon::parse($validated['new_clock_in'])->format('H:i')
            && $attendance->clock_out_at?->format('H:i') === Carbon::parse($validated['new_clock_out'])->format('H:i')
            && $originalBreaks === $newBreaks;

        // dd($noChange);

        if ($noChange) {
            return redirect()
                ->back() // または route('attendance.detail', $id)
                ->withInput() // 入力値を保持
                ->withErrors(['comment' => '変更がありません']);
        }

        // 4. 不要な丸括弧を削除
        $correction = AttendanceCorrection::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'clock_in_at' => $date.' '.$validated['new_clock_in'],
            'clock_out_at' => $date.' '.$validated['new_clock_out'],
            'approval_status' => '承認待ち',
            'comment' => $validated['comment'] ?? null,
        ]);

        foreach ($newBreakIns as $i => $breakIn) {
            $breakOut = $newBreakOuts[$i] ?? null;

            if (! $breakIn || ! $breakOut) {
                continue;
            }

            ProposalBreakCorrection::create([
                'attendance_correction_id' => $correction->id,
                'proposal_break_id' => $breaks[$i]->id ?? null,
                'break_start_at' => $date.' '.$breakIn,
                'break_end_at' => $date.' '.$breakOut,
            ]);

        }

        return redirect()->route('attendance.detail', $attendance->id)
            ->with('success', '勤怠の修正を申請しました');
    }

    public function approveStampCorrectionRequest($id)
    {
        Gate::authorize('approve', AttendanceCorrection::class);

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

        return view('user.user-detail', compact('data', 'user'));
    }

    public function detail($id)
    {

        $user = Auth::user();

        if (Auth::guard('admin')->check()) {
            return app(AdminAttendanceController::class)->detail($id);
        }

        $attendance = Attendance::with('breaks')->FindOrFail($id);

        $application = $attendance->attendanceCorrections()
            ->with('proposalBreaks')
            ->latest()
            ->first();

        Gate::authorize('view', $attendance);

        $source = $application ? $application->proposalBreaks : $attendance->breaks;

        $breaks = $source->map(function ($break) {
            return [
                'id' => $break->id,
                'break_in' => $break->break_start_at ? Carbon::parse($break->break_start_at)->format('H:i') : null,
                'break_out' => $break->break_end_at ? Carbon::parse($break->break_end_at)->format('H:i') : null,
            ];
        });

        $data = ([
            'application' => $application,
            'id' => $attendance->id,
            'year' => $attendance->clock_in_at->format('Y 年'),
            'date' => $attendance->clock_in_at->locale('ja')->translatedFormat('n/j(D)'),
            'clock_in' => $attendance->clock_in_at->format('H:i'),
            'clock_out' => $attendance->clock_out_at->format('H:i'),
            'breaks' => $breaks,
            'comment' => $application?->comment,
        ]);

        return view('user.user-detail', compact('user', 'data'));
    }
}
