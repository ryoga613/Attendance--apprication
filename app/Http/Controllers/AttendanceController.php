<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ProposalBreak;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    //
    public function index(Request $request)
    {
        $user = Auth::user();

        $date = $request->query('date')
            ? Carbon::createFromFormat('!Y-m', $request->query('date'))
            : now()->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendanceRecords = Attendance::with('proposalBreaks')
            ->where('user_id', $user->id)
            ->whereYear('work_date', $date->year)
            ->whereMonth('work_date', $date->month)
            ->orderBy('work_date')
            ->get();

        $formattedAttendanceRecords = $attendanceRecords->map(function ($attendance) {
            $clockIn = $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at) : null;
            $clockOut = $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at) : null;

            $breakSeconds = 0;
            foreach ($attendance->proposalBreaks as $break) {
                if ($break->break_start_at && $break->break_end_at) {
                    $breakSeconds += Carbon::parse($break->break_end_at)
                        ->diffInSeconds(Carbon::parse($break->break_start_at));
                }
            }

            $workSeconds = ($clockIn && $clockOut)
                ? max($clockOut->diffInSeconds($clockIn) - $breakSeconds, 0)
                : null;

            return [
                'id' => $attendance->id,
                'date' => Carbon::parse($attendance->work_date)->locale('ja')->isoFormat('MM/DD(ddd)'),
                'clock_in' => $clockIn ? $clockIn->format('H:i') : '',
                'clock_out' => $clockOut ? $clockOut->format('H:i') : '',
                'total_break_time' => $breakSeconds > 0 ? gmdate('H:i:s', $breakSeconds) : null,
                'total_time' => $workSeconds !== null ? gmdate('H:i:s', $workSeconds) : null,
            ];
        });

        return view('user.user-attendance-list', compact('formattedAttendanceRecords', 'previousMonth', 'nextMonth', 'date'));
    }

    public function attendanceRegisterForm()
    {
        $todayAttendance = Attendance::where('user_id', auth()->id())
            ->whereDate('work_date', Carbon::today())
            ->first();
        $user = auth()->user();
        $formattedDate = Carbon::now();
        $formattedTime = Carbon::now();

        return view('user.attendance-register', compact('todayAttendance', 'user', 'formattedDate', 'formattedTime'));
    }

    public function detail($id)
{
    $user = Auth::user();

    // 自分の勤怠だけ取得(他人のIDを直接指定されても見せない)
    $attendance = Attendance::with('proposalBreaks')
        ->where('user_id', $user->id)
        ->findOrFail($id);

    // 休憩時間の合計(分)
    $breakMinutes = $attendance->proposalBreaks
        ->filter(fn ($b) => $b->break_start_at && $b->break_end_at)
        ->sum(fn ($b) => Carbon::parse($b->break_start_at)
            ->diffInMinutes(Carbon::parse($b->break_end_at)));

    $breakTime = sprintf('%d:%02d', intdiv($breakMinutes, 60), $breakMinutes % 60);

    // 承認待ちの修正申請(リレーション名は実際のものに合わせてください)
    $application = $attendance->attendanceCorrections()
        ->where('approval_status', '承認待ち')
        ->latest()
        ->first();

    $data = [
        'id'          => $attendance->id,
        'application' => $application,
        'year'        => Carbon::parse($attendance->work_date)->format('Y年'),
        'date'        => Carbon::parse($attendance->work_date)->format('n月j日'),
        'clock_in'    => optional($attendance->clock_in_at)->format('H:i'),
        'clock_out'   => optional($attendance->clock_out_at)->format('H:i'),
        'breaks'      => $attendance->proposalBreaks->map(fn ($b) => [
            'break_in'  => optional($b->break_start_at)->format('H:i'),
            'break_out' => optional($b->break_end_at)->format('H:i'),
        ])->values()->all(),
        'break_time'  => $breakTime,
        'comment'     => $attendance->comment,
    ];
    // dd(($attendance->proposalBreaks)->first());

    return view('user.user-detail', compact('user', 'data'));
}

    public function store(Request $request)
    {
        $user = Auth::user();
        $action = $request->input('action');

        return match ($action) {
            'clock_in' => $this->clockIn($user),
            'clock_out' => $this->clockOut($user),
            'break_in' => $this->breakIn($user),
            'break_out' => $this->breakOut($user),
            default => redirect()->back($user),
        };
    }

    private function clockIn($user)
    {
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now(),
            'clock_out_at' => null,
        ]);

        $user->update([
            'attendance_status' => '出勤中',
        ]);

        return redirect()->route('attendance.register.form');
    }

    private function clockOut($user)
    {

        $attendance = Attendance::where('user_id', $user->id)->latest()->first();
        $attendance->update([
            'clock_out_at' => now(),
        ]);

        $user->update([
            'attendance_status' => '退勤済',
        ]);

        return redirect()->route('attendance.register.form');
    }

    private function breakIn($user)
    {

        $attendance = Attendance::where('user_id', $user->id)->latest()->first();
        $break = [
            'attendance_id' => $attendance->id,
            'break_start_at' => now(),
            'break_end_at' => null,
        ];

        ProposalBreak::create($break);

        $user->update([
            'attendance_status' => '休憩中',
        ]);

        return redirect()->route('attendance.register.form');
    }

    private function breakOut($user)
    {

        $attendance = Attendance::where('user_id', $user->id)->latest()->first();
        $updatedBreak = [
            'break_end_at' => now(),
        ];

        $Break = ProposalBreak::where('attendance_id', $attendance->id)->whereNull('break_end_at')->latest();

        $Break->update($updatedBreak);

        $user->update([
            'attendance_status' => '出勤中',
        ]);

        return redirect()->route('attendance.register.form');
    }
}
