<?php

namespace App\Http\Controllers\UserControllers;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserAttendanceController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Attendance::class);

        $user = Auth::user();

        $date = $request->query('date')
            ? Carbon::createFromFormat('!Y-m', $request->query('date'))
            : now()->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendanceRecords = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereYear('work_date', $date->year)
            ->whereMonth('work_date', $date->month)
            ->orderBy('work_date')
            ->get();

        // 休憩・勤務時間の計算はAttendanceモデルにまとめている
        $formattedAttendanceRecords = $attendanceRecords->map(fn ($attendance) => [
            'id' => $attendance->id,
            'date' => $attendance->work_date->locale('ja')->format('Y 年 m 月 d 日'),
            'clock_in' => $attendance->clock_in ?? '',
            'clock_out' => $attendance->clock_out ?? '',
            'total_break_time' => $attendance->total_break_time,
            'total_time' => $attendance->total_time,
        ]);

        return view('user.user-attendance-list', compact('formattedAttendanceRecords', 'previousMonth', 'nextMonth', 'date'));
    }

    public function attendanceRegisterForm()
    {
        Gate::authorize('create', Attendance::class);

        $user = auth()->user();
        $todayAttendance = $this->todayAttendance($user);
        $status = $this->statusOf($todayAttendance)->value;

        // 退勤し忘れた過去の勤怠(承認待ちの修正申請があるものは除く)は、修正申請を促す
        $unfinishedAttendances = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', '<', Carbon::today())
            ->whereNull('clock_out_at')
            ->whereDoesntHave('attendanceCorrections', fn ($query) => $query->where('approval_status', ApprovalStatus::PENDING))
            ->orderBy('work_date')
            ->get();

        $formattedDate = Carbon::now()->locale('ja')->translatedFormat('Y 年 n月 j日（D）');
        $formattedTime = Carbon::now();

        return view('user.attendance-register', compact('todayAttendance', 'user', 'status', 'unfinishedAttendances', 'formattedDate', 'formattedTime'));
    }

    public function detail($id)
    {
        $application = AttendanceCorrection::with('proposalBreaks')->findOrFail($id);

        Gate::authorize('view', $application);

        $attendance = $application->AttendanceRecord;

        $formatBreak = fn ($break) => [
            'id' => $break->id,
            'break_in' => $break->break_start_at ? Carbon::parse($break->break_start_at)->format('H:i') : null,
            'break_out' => $break->break_end_at ? Carbon::parse($break->break_end_at)->format('H:i') : null,
        ];

        // 承認済みなら、修正申請は表示せず元の勤怠データを使う
        $isApproved = $application->approval_status === ApprovalStatus::APPROVED;

        $breaks = $isApproved
            ? $attendance->breaks->map($formatBreak)
            : $application->proposalBreaks->map($formatBreak);

        $data = [
            'application' => $isApproved ? null : $application,
            'id' => $attendance->id,
            'date' => $attendance->clock_in_at->locale('ja')->translatedFormat('n/j(D)'),
            'year' => $attendance->clock_in_at->format('Y 年'),
            'clock_in' => $attendance->clock_in_at?->format('H:i'),
            'clock_out' => $attendance->clock_out_at?->format('H:i'),
            'breaks' => $breaks,
            'comment' => $isApproved ? null : $application->comment,
        ];

        $user = Auth::user();

        return view('user.user-detail', compact('data', 'user'));
    }

    public function store(Request $request)
    {
        Gate::authorize('store', Attendance::class);

        $user = Auth::user();
        $attendance = $this->todayAttendance($user);
        $status = $this->statusOf($attendance);

        // 今の状態で押せないボタンは何もしない(二重送信・古い画面からの送信対策)
        match ([$request->input('action'), $status]) {
            ['clock_in', AttendanceStatus::OFF_DUTY] => $this->clockIn($user),
            ['clock_out', AttendanceStatus::ON_DUTY] => $this->clockOut($user, $attendance),
            ['break_in', AttendanceStatus::ON_DUTY] => $this->breakIn($user, $attendance),
            ['break_out', AttendanceStatus::ON_BREAK] => $this->breakOut($user, $attendance),
            default => null,
        };

        return redirect()->route('attendance.register.form');
    }

    // 今日の勤怠
    private function todayAttendance($user): ?Attendance
    {
        return Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereDate('work_date', Carbon::today())
            ->first();
    }

    // 今日の勤怠から打刻の状態を求める(日付が変われば勤務外に戻る)
    private function statusOf(?Attendance $attendance): AttendanceStatus
    {
        return match (true) {
            ! $attendance => AttendanceStatus::OFF_DUTY,
            (bool) $attendance->clock_out_at => AttendanceStatus::CLOCKED_OUT,
            $attendance->breaks->contains(fn ($b) => ! $b->break_end_at) => AttendanceStatus::ON_BREAK,
            default => AttendanceStatus::ON_DUTY,
        };
    }

    private function clockIn($user): void
    {
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now(),
            'clock_out_at' => null,
        ]);

        $user->update(['attendance_status' => AttendanceStatus::ON_DUTY]);
    }

    private function clockOut($user, Attendance $attendance): void
    {
        $attendance->update(['clock_out_at' => now()]);

        $user->update(['attendance_status' => AttendanceStatus::CLOCKED_OUT]);
    }

    private function breakIn($user, Attendance $attendance): void
    {
        $attendance->breaks()->create([
            'break_start_at' => now(),
            'break_end_at' => null,
        ]);

        $user->update(['attendance_status' => AttendanceStatus::ON_BREAK]);
    }

    private function breakOut($user, Attendance $attendance): void
    {
        $attendance->breaks()->whereNull('break_end_at')->update(['break_end_at' => now()]);

        $user->update(['attendance_status' => AttendanceStatus::ON_DUTY]);
    }
}
