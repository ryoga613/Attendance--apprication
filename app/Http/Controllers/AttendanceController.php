<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\ProposalBreak;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    //
    public function index()
    {
        $formattedAttendanceRecords = Attendance::where('user_id', auth()->id())
            ->whereYear('work_date', Carbon::now()->year)
            ->whereMonth('work_date', Carbon::now()->month)
            ->orderBy('work_date', 'desc')
            ->get();

        $previousMonth = Carbon::now()->subMonth();
        $nextMonth = Carbon::now()->addMonth();
        $date = Carbon::now();

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

    public function store(Request $request)
    {
        $user = Auth::user();
        $action = $request->input('action');

        return match ($action) {
            'clock_in' => $this->clockIn($user),   // 出勤処理へ
            'clock_out' => $this->clockOut($user),  // 退勤処理へ
            'break_in' => $this->breakIn($user),   // 休憩入処理へ
            'break_out' => $this->breakOut($user),// 休憩終処理へ
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
