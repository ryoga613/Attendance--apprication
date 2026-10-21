<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ProposalBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ゲストはレポートページにアクセスできない()
    {
        $response = $this->get('/attendance/report');

        $response->assertStatus(302)
            ->assertRedirect('/login');
    }

    public function test_認証ユーザーの統計情報が正しく計算される(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00'));

        $user = User::factory()->create();

        $patterns = [
            ['09:00', '18:00'], // 通常
            ['09:00', '18:00'], // 通常
            ['09:00', '20:00'], // 残業
            ['09:30', '18:00'], // 遅刻
            ['09:00', '17:00'], // 早退
            ['08:00', '21:00'], // 長時間労働
        ];

        foreach ($patterns as $i => [$clockIn, $clockOut]) {
            $date = '2026-10-0'.($i + 1);

            $attendance = Attendance::create([
                'user_id' => $user->id,
                'work_date' => $date,
                'clock_in_at' => "{$date} {$clockIn}:00",
                'clock_out_at' => "{$date} {$clockOut}:00",
            ]);

            // 休憩は全日 12:00〜13:00 の1時間
            ProposalBreak::create([
                'attendance_id' => $attendance->id,
                'break_start_at' => "{$date} 12:00:00",
                'break_end_at' => "{$date} 13:00:00",
            ]);
        }

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertStatus(200);

        $summary = $response->viewData('summary');
        $anomalies = $response->viewData('anomalies');

        $this->assertSame(3150, $summary['total_work_minutes']);
        $this->assertSame(360, $summary['total_overtime_minutes']);

        $this->assertSame(1, $anomalies['late_count']); // 遅刻数
        $this->assertSame(1, $anomalies['early_leave_count']); // 早退数
        $this->assertSame(1, $anomalies['long_work_count']); // 長時間労働数

    }

    public function test_勤怠記録がないユーザーで安全に処理される()
    {

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('attendance/report')
            ->assertStatus(200)
            ->assertViewHas('summary', function ($summary) {
                return $summary['total_work_minutes'] === 0
                    && $summary['total_overtime_minutes'] === 0
                    && $summary['avg_work_minutes'] === 0
                    && $summary['avg_overtime_minutes'] === 0;
            })
            ->assertViewHas('monthlyTrend', function ($monthlyTrend) {
                return count($monthlyTrend) === 6;
            })
            ->assertViewHas('anomalies', function ($anomalies) {
                return $anomalies['late_count'] === 0
                    && $anomalies['early_leave_count'] === 0
                    && $anomalies['long_work_count'] === 0;
            });
    }
}
