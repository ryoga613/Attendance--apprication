<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
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

    public function test_不正な形式の日付を指定すると今日の勤怠一覧が表示される()
    {
        foreach (['abc', '2026-10-32', '2026/10/15'] as $invalid) {
            $this->openList('?date='.urlencode($invalid))
                ->assertStatus(200)
                ->assertSee('2026/10/16')
                ->assertSee('当日さん');
        }
    }

    public function test_不正な形式の月を指定すると今月のスタッフの勤怠一覧が表示される()
    {
        $user = User::where('name', '当日さん')->first();

        foreach (['abc', '2026-13', '2026/10'] as $invalid) {
            $this->actingAs($this->admin, 'admin')
                ->get("admin/attendance/staff/{$user->id}?date=".urlencode($invalid))
                ->assertStatus(200)
                ->assertViewHas('previousMonth', '2026-09')
                ->assertViewHas('nextMonth', '2026-11');
        }
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

    public function test_勤務詳細の名前には管理者ではなくスタッフの名前が表示される()
    {
        $this->admin->update(['name' => '管理者花子']);

        $staff = User::factory()->create(['name' => 'スタッフ次郎']);
        $attendance = Attendance::factory()->create([
            'user_id' => $staff->id,
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get("admin/attendance/{$attendance->id}")
            ->assertStatus(200)
            ->assertSee('スタッフ次郎')
            ->assertDontSee('管理者花子');
    }

    // ---------- 動線上で選んだ勤怠が詳細に表示される ----------

    // 「当日さん」の勤怠に休憩を2つ付ける
    private function todayAttendanceWithBreaks(): Attendance
    {
        $attendance = Attendance::whereDate('work_date', '2026-10-16')->firstOrFail();
        ProposalBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-10-16 12:00:00',
            'break_end_at' => '2026-10-16 13:00:00',
        ]);
        ProposalBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-10-16 15:00:00',
            'break_end_at' => '2026-10-16 15:15:00',
        ]);

        return $attendance;
    }

    private function assertDetailMatches($response, string $name, array $times): void
    {
        $response->assertStatus(200)
            ->assertViewIs('admin.admin-detail')
            ->assertSee('value="'.$name.'"', false)
            ->assertSee('value="2026 年"', false)
            ->assertSee('value="10/16(金)"', false);

        $record = $response->viewData('attendanceRecord');
        $this->assertSame($times['clock_in'], $record['clock_in']);
        $this->assertSame($times['clock_out'], $record['clock_out']);
        $this->assertSame($times['breaks'], $record['breaks']);
    }

    public function test_日次勤怠一覧で選んだ勤怠の詳細が表示される()
    {
        $attendance = $this->todayAttendanceWithBreaks();
        $detailUrl = url('/admin/attendance/'.$attendance->id);

        // 一覧の「詳細」リンクが、その日のその人の勤怠を指している
        $this->openList('?date=2026-10-16')->assertSee('href="'.$detailUrl.'"', false);

        $this->assertDetailMatches(
            $this->actingAs($this->admin, 'admin')->get($detailUrl),
            '当日さん',
            [
                'clock_in' => '09:00',
                'clock_out' => '18:00',
                'breaks' => [
                    ['break_in' => '12:00', 'break_out' => '13:00'],
                    ['break_in' => '15:00', 'break_out' => '15:15'],
                ],
            ]
        );
    }

    public function test_スタッフ別勤怠一覧で選んだ勤怠の詳細が表示される()
    {
        $attendance = $this->todayAttendanceWithBreaks();
        $detailUrl = url('/admin/attendance/'.$attendance->id);

        $this->actingAs($this->admin, 'admin')
            ->get('/admin/attendance/staff/'.$attendance->user_id.'?date=2026-10')
            ->assertSee('href="'.$detailUrl.'"', false);

        $this->assertDetailMatches(
            $this->actingAs($this->admin, 'admin')->get($detailUrl),
            '当日さん',
            [
                'clock_in' => '09:00',
                'clock_out' => '18:00',
                'breaks' => [
                    ['break_in' => '12:00', 'break_out' => '13:00'],
                    ['break_in' => '15:00', 'break_out' => '15:15'],
                ],
            ]
        );
    }

    // ---------- 修正・承認の結果が詳細に反映される ----------

    public function test_管理者が直接修正した内容が詳細に反映される()
    {
        $attendance = $this->todayAttendanceWithBreaks();

        $this->actingAs($this->admin, 'admin')->post('/admin/attendance/'.$attendance->id, [
            'new_clock_in' => '08:45',
            'new_clock_out' => '17:30',
            'new_break_in' => ['12:15', '15:00', '16:00'],
            'new_break_out' => ['13:15', '15:15', '16:10'],
            'comment' => '打刻漏れのため修正',
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($this->admin, 'admin')->get('/admin/attendance/'.$attendance->id);

        $this->assertDetailMatches($response, '当日さん', [
            'clock_in' => '08:45',
            'clock_out' => '17:30',
            'breaks' => [
                ['break_in' => '12:15', 'break_out' => '13:15'],
                ['break_in' => '15:00', 'break_out' => '15:15'],
                ['break_in' => '16:00', 'break_out' => '16:10'],
            ],
        ]);
        $this->assertSame('打刻漏れのため修正', $response->viewData('attendanceRecord')['comment']);
    }

    public function test_承認した申請の内容が詳細に反映される()
    {
        $attendance = $this->todayAttendanceWithBreaks();
        $staff = $attendance->user;

        $this->actingAs($staff)->post('/attendance/'.$attendance->id, [
            'new_clock_in' => '10:00',
            'new_clock_out' => '19:00',
            'new_break_in' => ['12:30', ''],
            'new_break_out' => ['13:30', ''],
            'comment' => '電車遅延のため',
        ])->assertSessionHasNoErrors();

        $correction = AttendanceCorrection::firstOrFail();
        $this->actingAs($this->admin, 'admin')
            ->post('/stamp_correction_request/approve/'.$correction->id)
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($this->admin, 'admin')->get('/admin/attendance/'.$attendance->id);

        $this->assertDetailMatches($response, '当日さん', [
            'clock_in' => '10:00',
            'clock_out' => '19:00',
            'breaks' => [
                ['break_in' => '12:30', 'break_out' => '13:30'],
            ],
        ]);
        $this->assertSame('電車遅延のため', $response->viewData('attendanceRecord')['comment']);
    }

    public function test_255文字を超える備考でも直接修正できる()
    {
        $attendance = $this->todayAttendanceWithBreaks();
        $comment = str_repeat('あ', 1000);

        $this->actingAs($this->admin, 'admin')->post('/admin/attendance/'.$attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00', '15:00'],
            'new_break_out' => ['13:00', '15:15'],
            'comment' => $comment,
        ])->assertSessionHasNoErrors();

        $this->assertSame($comment, $attendance->fresh()->comment);
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
