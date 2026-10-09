<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Carbon\Carbon;

class AdminAttendanceController extends Controller
{
    public function index()
    {
        $date = Carbon::parse(request()->get('date', now()->toDateString()));

        $previousDay = $date->copy()->subDay();
        $nextDay = $date->copy()->addDay();

        $attendanceRecords = Attendance::with('breaks')
            ->whereDate('work_date', $date)
            ->get();

        $users = User::all();

        return view('admin.admin-attendance-list', compact('attendanceRecords', 'date', 'previousDay', 'nextDay', 'users'));
    }

    public function detail($id)
    {
        $attendanceRecord = Attendance::with('breaks')->findOrFail($id);
        $user = $attendanceRecord->user;

        return view('admin.admin-detail', compact('attendanceRecord', 'user'));
    }

    public function staffList()
    {
        $users = User::all();

        return view('admin.staff-list', compact('users'));
    }

    public function staffAttendance($id)
    {
        $user = User::findOrFail($id);
        $attendanceRecords = Attendance::with('breaks')->where('user_id', $id)->whereMonth('work_date', request()->get('month', now()->month))->get();

        $date = $attendanceRecords->first()->work_date;

        $formattedAttendanceRecords = $attendanceRecords->map(function ($record) {
            return [
                'id' => $record->id,
                'date' => $record->work_date,
                'clock_in' => $record->clock_in_at ? $record->clock_in_at->format('H:i') : null,
                'clock_out' => $record->clock_out_at ? $record->clock_out_at->format('H:i') : null,
                'break_time' => $record->break_time,
                'work_time' => $record->work_time,
                'total_break_time' => $record->total_break_time,
                'total_time' => $record->total_time,
            ];
        });
        $previousMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();

        return view('admin.staff-attendance-list', compact('user', 'attendanceRecords', 'date', 'formattedAttendanceRecords', 'previousMonth', 'nextMonth'));
    }

    
}
