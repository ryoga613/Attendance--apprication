<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ProposalBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // 今日を固定する
        Carbon::setTestNow('2026-10-16 10:00:00');

        $this->admin = User::factory()->create(['admin_status' => true]);

        $this->makeAttendance('前日さん', '2026-10-15', '08:30');
        $this->makeAttendance('当日さん', '2026-10-16', '09:00');
        $this->makeAttendance('翌日さん', '2026-10-17', '10:15');
    }

    private function makeAttendance(string $name, string $date, string $clockIn): void
    {
        $user = User::factory()->create(['name' => $name]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in_at' => "$date $clockIn:00",
            'clock_out_at' => "$date 18:00:00",
        ]);
    }

    private function openList(string $query = '')
    {
        return $this->actingAs($this->admin, 'admin')
            ->get('/admin/attendance/list'.$query);
    }

    public function test_現在の日付が表示される()
    {
        $this->openList()
            ->assertStatus(200)
            ->assertSee('2026/10/16')
            ->assertSee('当日さん')
            ->assertSee('09:00');
    }

    public function test_前日を押すと前日の勤怠が表示される()
    {
        // ボタン(リンク)が前日の日付を指している
        $this->openList()->assertSee('date=2026-10-15');

        // 前日で開くと、前日の勤怠が出る
        $this->openList('?date=2026-10-15')
            ->assertSee('2026/10/15')
            ->assertSee('前日さん')
            ->assertSee('08:30')
            ->assertDontSee('当日さん');
    }

    public function test_翌日を押すと翌日の勤怠が表示される()
    {
        $this->openList()->assertSee('date=2026-10-17');

        $this->openList('?date=2026-10-17')
            ->assertSee('2026/10/17')
            ->assertSee('翌日さん')
            ->assertSee('10:15')
            ->assertDontSee('当日さん');
    }

    public function test_勤務詳細が見れる()
    {
        $attendance = Attendance::factory()->create([
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
        ]);

        $proposalBreak = ProposalBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => now()->setTime(12, 0),
            'break_end_at' => now()->setTime(13, 0),
        ]);
        $id = $attendance->id;
        $response = $this->actingAs($this->admin, 'admin')
            ->get("admin/attendance/{$id}");

        $record = $response->viewData('attendanceRecord');
        $response->assertStatus(200);
        $response->assertViewIs('admin.admin-detail');
        $this->assertEquals('12:00', $record['breaks'][0]['break_in']);
        $this->assertEquals('13:00', $record['breaks'][0]['break_out']);
    }

    public function test_スタッフ一覧が見れる()
    {
        $response = $this->actingAs($this->admin, 'admin')->get('admin/staff/list');

        $response->assertStatus(200);
        $response->assertViewIs('admin.staff-list');
        $response->assertViewHas('users');
    }

    public function test_スタッフの勤怠一覧が見れる()
    {
        $user = User::where('name', '当日さん')->first();

        $id = $user->id;
        $response = $this->actingAs($this->admin, 'admin')->get("admin/attendance/staff/{$id}");

        $response->assertStatus(200);
        $response->assertViewIs('admin.staff-attendance-list');
        $response->assertViewHasAll([
            'user',
            'attendanceRecords',
            'date',
            'formattedAttendanceRecords',
            'previousMonth',
            'nextMonth',
        ]);

    }

    public function test_未退勤の勤務詳細も見れる()
    {
        $attendance = Attendance::factory()->create([
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => null,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get("admin/attendance/{$attendance->id}");

        $response->assertStatus(200);
        $this->assertNull($response->viewData('attendanceRecord')['clock_out']);
    }

    public function test_勤怠がない月でもスタッフの勤怠一覧が見れる()
    {
        $user = User::where('name', '当日さん')->first();

        $response = $this->actingAs($this->admin, 'admin')->get("admin/attendance/staff/{$user->id}?date=2026-08");

        $response->assertStatus(200);
        $response->assertViewHas('formattedAttendanceRecords', fn ($records) => $records->isEmpty());
        $response->assertViewHas('previousMonth', '2026-07');
        $response->assertViewHas('nextMonth', '2026-09');
    }

    public function test_スタッフの勤怠一覧に別の年の同じ月が混ざらない()
    {
        $user = User::where('name', '当日さん')->first();
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2025-10-16',
            'clock_in_at' => '2025-10-16 09:00:00',
            'clock_out_at' => '2025-10-16 18:00:00',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get("admin/attendance/staff/{$user->id}?date=2026-10");

        $response->assertViewHas('formattedAttendanceRecords', fn ($records) => $records->count() === 1
            && $records->first()['date']->format('Y-m-d') === '2026-10-16');
    }

    public function test_スタッフ一覧に管理者は表示されない()
    {
        $response = $this->actingAs($this->admin, 'admin')->get('admin/staff/list');

        $response->assertViewHas('users', fn ($users) => $users->doesntContain('id', $this->admin->id)
            && $users->contains('name', '当日さん'));
    }

    public function test_スタッフの月の勤怠を_cs_vで出力できる()
    {
        $user = User::where('name', '当日さん')->first();
        $attendance = Attendance::where('user_id', $user->id)->first();
        ProposalBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-10-16 12:00:00',
            'break_end_at' => '2026-10-16 13:00:00',
        ]);
        // 別の月の勤怠は出力されない
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-09-01',
            'clock_in_at' => '2026-09-01 09:00:00',
            'clock_out_at' => '2026-09-01 18:00:00',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->post('/export', [
            'user_id' => $user->id,
            'year_month' => '2026-10',
        ]);

        $response->assertOk();
        $response->assertDownload("attendance_{$user->id}_2026-10.csv");

        $lines = explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $response->streamedContent())));
        $this->assertSame(['日付,出勤,退勤,休憩,合計', '2026/10/16,09:00,18:00,01:00,08:00'], $lines);
    }

    public function test_一般ユーザーは_cs_vを出力できない()
    {
        $user = User::where('name', '当日さん')->first();

        $this->actingAs($user)->post('/export', ['user_id' => $user->id, 'year_month' => '2026-10'])
            ->assertRedirect(route('login'));
    }
}
