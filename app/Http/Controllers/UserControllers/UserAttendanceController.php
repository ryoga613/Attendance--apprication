<?php

namespace App\Http\Controllers\UserControllers;

use App\http\Controllers\AdminControllers\AdminAttendanceController;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\ProposalBreak;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAttendanceController extends Controller
{
    public function index(Request $request)
    {
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

        $formattedAttendanceRecords = $attendanceRecords->map(function ($attendance) {
            $clockIn = $attendance->clock_in_at ? Carbon::parse($attendance->clock_in_at) : null;
            $clockOut = $attendance->clock_out_at ? Carbon::parse($attendance->clock_out_at) : null;

            $breakSeconds = 0;
            foreach ($attendance->breaks as $break) {
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
                'date' => Carbon::parse($attendance->work_date)->locale('ja')->format('Y 年 m 月 d 日'),
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
        $application = AttendanceCorrection::with('proposalBreaks')->findOrFail($id);
        $attendance = $application->AttendanceRecord;

        $breaks = $attendance->breaks->map(function ($break) {
            return [
                'id' => $break->id,
                'break_in' => $break->break_start_at ? Carbon::parse($break->break_start_at)->format('H:i') : null,
                'break_out' => $break->break_end_at ? Carbon::parse($break->break_end_at)->format('H:i') : null,
            ];
        });
        $data = ([
            'application'=> $application,
            'id' => $attendance->id,
            'date' => $attendance->work_date->locale('ja')->translatedFormat('n/j(D)'),
            'year' => $attendance->work_date->format('Y 年'),
            'clock_in' => $attendance->clock_in_at->format('H:i'),
            'clock_out' => $attendance->clock_out_at->format('H:i'),
            'breaks'=> $breaks,
            'comment'=> $application->comment,

            ]);

        $user = Auth::user();
        return view('user.user-detail',compact('data','user'));
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
        if ($user->attendance_status() !== '勤務外') {
            return redirect()->route('attendance.register.form');
        }
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

    public function approveStampCorrectionRequest($id)
    {
        $user = Auth::user();
        $application = AttendanceCorrection::with('proposalBreaks')->findOrFail($id);

        return view('admin.admin-application-detail', compact('application', 'user'));
    }
}
