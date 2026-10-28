<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AdminAttendanceController extends Controller
{
    public function index()
    {
        Gate::authorize('manage', Attendance::class);

        $date = Carbon::parse(request()->get('date', now()->toDateString()));

        $previousDay = $date->copy()->subDay();
        $nextDay = $date->copy()->addDay();

        $attendanceRecords = Attendance::with('breaks')
            ->whereDate('work_date', $date)
            ->get();

        $users = User::where('admin_status', false)->get();

        return view('admin.admin-attendance-list', compact('attendanceRecords', 'date', 'previousDay', 'nextDay', 'users'));
    }

    public function detail($id)
    {

        $attendance = Attendance::with('breaks')->findOrFail($id);

        $application = AttendanceCorrection::where('attendance_id', $id)->first();

        Gate::authorize('view', $attendance);

        $attendanceRecord = [
            'application' => $application,
            'id' => $attendance->id,
            'year' => $attendance->year,
            'date' => (string) $attendance->date->locale('ja')->translatedFormat('n/j(D)'),
            'clock_in' => $attendance->clock_in_at?->format('H:i'),
            'clock_out' => $attendance->clock_out_at?->format('H:i'),
            'breaks' => $attendance->breaks->map(fn ($b) => [
                'break_in' => $b->break_start_at?->format('H:i'),
                'break_out' => $b->break_end_at?->format('H:i'),
            ])->values()->all(),
            'comment' => $attendance->comment,
        ];
        $user = Auth::user();

        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }

    public function staffList()
    {
        Gate::authorize('manage', Attendance::class);

        $users = User::where('admin_status', false)->get();

        return view('admin.staff-list', compact('users'));
    }

    public function staffAttendance($id)
    {
        Gate::authorize('manage', Attendance::class);

        $user = User::findOrFail($id);

        $date = request()->query('date')
            ? Carbon::createFromFormat('!Y-m', request()->query('date'))
            : now()->startOfMonth();

        $attendanceRecords = $this->monthlyRecords($user->id, $date);

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
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        return view('admin.staff-attendance-list', compact('user', 'attendanceRecords', 'date', 'formattedAttendanceRecords', 'previousMonth', 'nextMonth'));
    }

    // スタッフ別勤怠一覧と同じ月の勤怠をCSVで出力する
    public function exportCsv(Request $request)
    {
        Gate::authorize('manage', Attendance::class);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year_month' => ['required', 'date_format:Y-m'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $month = Carbon::createFromFormat('!Y-m', $validated['year_month']);
        $attendanceRecords = $this->monthlyRecords($user->id, $month);

        $fileName = "attendance_{$user->id}_{$month->format('Y-m')}.csv";

        return response()->streamDownload(function () use ($attendanceRecords) {
            $handle = fopen('php://output', 'w');

            // Excelで開いても文字化けしないようにBOMを付ける
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['日付', '出勤', '退勤', '休憩', '合計']);

            foreach ($attendanceRecords as $record) {
                fputcsv($handle, [
                    $record->work_date->format('Y/m/d'),
                    $record->clock_in ?? '',
                    $record->clock_out ?? '',
                    $record->total_break_time ?? '',
                    $record->total_time ?? '',
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // 指定したユーザーの、指定した月の勤怠
    private function monthlyRecords(int $userId, Carbon $month)
    {
        return Attendance::with('breaks')
            ->where('user_id', $userId)
            ->whereYear('work_date', $month->year)
            ->whereMonth('work_date', $month->month)
            ->orderBy('work_date')
            ->get();
    }
}
