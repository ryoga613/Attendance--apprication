<?php

namespace App\Http\Controllers\UserControllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;

class AttendanceReportController extends Controller
{
    public function index()
    {
        $start = now()->startOfMonth()->subMonths(5); // 今月を含めて6か月分

        $records = Attendance::with('breaks')
            ->where('user_id', auth()->id())
            ->where('work_date', '>=', $start->toDateString())
            ->get();

        // 退勤済みの日だけ(合計・平均用)
        $completed = $records->whereNotNull('clock_out_at');

        // 月別の表
        $byMonth = $completed->groupBy(fn ($a) => $a->work_date->format('Y-m'));
        $monthlyTrend = [];

        for ($i = 0; $i < 6; $i++) {
            $month = $start->copy()->addMonths($i);
            $monthRecords = $byMonth->get($month->format('Y-m'), collect());

            $monthlyTrend[] = [
                'month' => $month->format('Y年n月'),
                'work_minutes' => $monthRecords->sum('total_work_minutes'),
                'overtime_minutes' => $monthRecords->sum('total_overtime_minutes'),
            ];
        }

        // 遅刻・早退・長時間労働の回数
        $anomalies = [
            'late_count' => $records->sum('late_count'),
            'early_leave_count' => $records->sum('early_leave_count'),
            'long_work_count' => $records->sum('long_work_count'),
        ];

        // 全体の集計
        $summary = [
            'total_work_minutes' => $completed->sum('total_work_minutes'),
            'total_overtime_minutes' => $completed->sum('total_overtime_minutes'),
            'avg_work_minutes' => (int) round($completed->avg('total_work_minutes') ?? 0),
            'avg_overtime_minutes' => (int) round($completed->avg('total_overtime_minutes') ?? 0),
        ];

        return view('reports.index', compact('summary', 'monthlyTrend', 'anomalies'));
    }
}
