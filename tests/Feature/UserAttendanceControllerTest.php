<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\ProposalBreak;
use App\Models\ProposalBreakCorrection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---------- データ作成用ヘルパー(setUpは使わず、各テストから呼ぶ) ----------

    private function createUser(array $override = []): User
    {
        return User::factory()->create(array_merge([
            'attendance_status' => '勤務外',
        ], $override));
    }

    // 今日の勤怠(9:00-18:00)を作る
    private function makeAttendance(User $user, array $override = []): Attendance
    {
        return Attendance::create(array_merge([
            'user_id' => $user->id,
            'work_date' => '2026-10-16',
            'clock_in_at' => '2026-10-16 09:00:00',
            'clock_out_at' => '2026-10-16 18:00:00',
        ], $override));
    }

    private function makeBreak(Attendance $attendance, $start = '12:00', $end = '13:00'): ProposalBreak
    {
        return ProposalBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => "2026-10-16 $start:00",
            'break_end_at' => $end ? "2026-10-16 $end:00" : null,
        ]);
    }

    private function makeCorrection(User $user, string $status): AttendanceCorrection
    {
        $attendance = $this->makeAttendance($user);
        $break = $this->makeBreak($attendance); // 元の休憩 12:00-13:00

        $correction = AttendanceCorrection::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'clock_in_at' => '2026-10-16 10:00:00',
            'clock_out_at' => '2026-10-16 19:00:00',
            'approval_status' => $status,
            'comment' => '電車遅延のため',
        ]);

        // 申請の休憩 12:30-13:30
        ProposalBreakCorrection::create([
            'attendance_correction_id' => $correction->id,
            'proposal_break_id' => $break->id,
            'break_start_at' => '2026-10-16 12:30:00',
            'break_end_at' => '2026-10-16 13:30:00',
        ]);

        return $correction;
    }

    // ---------- 勤怠一覧 ----------

    public function test_勤怠一覧に自分の勤怠だけが表示される()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser();
        $attendance = $this->makeAttendance($user);
        $this->makeBreak($attendance);

        // 他人の勤怠(表示されてはいけない)
        $other = $this->createUser();
        Attendance::create([
            'user_id' => $other->id,
            'work_date' => '2026-10-16',
            'clock_in_at' => '2026-10-16 08:00:00',
            'clock_out_at' => '2026-10-16 17:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertViewIs('user.user-attendance-list');
        $response->assertViewHas('formattedAttendanceRecords', function ($records) {
            $first = $records->first();

            return $records->count() === 1
                && $first['clock_in'] === '09:00'
                && $first['clock_out'] === '18:00'
                && $first['total_break_time'] === '01:00'
                && $first['total_time'] === '08:00';
        });
    }

    public function test_勤怠一覧の前月翌月が正しい()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertViewHas('previousMonth', '2026-09');
        $response->assertViewHas('nextMonth', '2026-11');
    }

    public function test_月を指定すると、その月の勤怠が表示される()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser();
        $this->makeAttendance($user); // 10月分

        $this->actingAs($user)->get('/attendance/list?date=2026-09')
            ->assertViewHas('formattedAttendanceRecords', fn ($r) => $r->count() === 0);
    }

    // ---------- 打刻画面 ----------

    public function test_打刻画面が表示される()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser();

        $this->actingAs($user)->get('/attendance')
            ->assertStatus(200)
            ->assertViewIs('user.attendance-register');
    }

    // ---------- 打刻 ----------

    public function test_出勤できる()
    {

        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser(['attendance_status' => '勤務外']);

        $response = $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $this->assertDatabaseHas('attendances', [
            'clock_in_at' => '2026-10-16 10:00:00',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'attendance_status' => '出勤中',
        ]);
    }

    public function test_出勤中は出勤を二重に押しても勤怠が増えない()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser(['attendance_status' => '出勤中']);
        $this->makeAttendance($user, ['clock_out_at' => null]);

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        // 他のデータの影響を受けないよう、このユーザーの勤怠だけを確認する
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }

    public function test_前日に退勤済みでも翌日は出勤できる()
    {
        Carbon::setTestNow('2026-10-17 09:00:00');

        // 前日(10/16)に退勤済みで、ステータスも退勤済のまま
        $user = $this->createUser(['attendance_status' => '退勤済']);
        $this->makeAttendance($user);

        $this->actingAs($user)->get('/attendance')
            ->assertViewHas('status', '勤務外');

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'clock_in_at' => '2026-10-17 09:00:00',
        ]);
    }

    public function test_出勤前に退勤や休憩を押しても何も起きない()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser();

        foreach (['clock_out', 'break_in', 'break_out'] as $action) {
            $this->actingAs($user)->post('/attendance', ['action' => $action])
                ->assertRedirect(route('attendance.register.form'));
        }

        $this->assertSame(0, Attendance::where('user_id', $user->id)->count());
    }

    public function test_休憩中は休憩入と退勤ができない()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser(['attendance_status' => '休憩中']);
        $attendance = $this->makeAttendance($user, ['clock_out_at' => null]);
        $this->makeBreak($attendance, '09:30', null);

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_out']);

        $this->assertSame(1, $attendance->breaks()->count());
        $this->assertNull($attendance->fresh()->clock_out_at);
    }

    public function test_休憩入できる()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser(['attendance_status' => '出勤中']);
        $attendance = $this->makeAttendance($user, ['clock_out_at' => null]);

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        $this->assertDatabaseHas('proposal_breaks', [
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-10-16 10:00:00',
            'break_end_at' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'attendance_status' => '休憩中',
        ]);
    }

    public function test_休憩戻できる()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser(['attendance_status' => '休憩中']);
        $attendance = $this->makeAttendance($user, ['clock_out_at' => null]);
        $break = $this->makeBreak($attendance, '09:30', null);

        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        $this->assertDatabaseHas('proposal_breaks', [
            'id' => $break->id,
            'break_end_at' => '2026-10-16 10:00:00',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'attendance_status' => '出勤中',
        ]);
    }

    public function test_退勤できる()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser(['attendance_status' => '出勤中']);
        $attendance = $this->makeAttendance($user, ['clock_out_at' => null]);

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_out']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_out_at' => '2026-10-16 10:00:00',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'attendance_status' => '退勤済',
        ]);
    }

    // ---------- 詳細(修正申請ID) ----------

    public function test_承認待ちの詳細には申請内容が表示される()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser();
        $correction = $this->makeCorrection($user, '承認待ち');

        $response = $this->actingAs($user)->get('/application/'.$correction->id);

        $response->assertStatus(200);
        $response->assertViewIs('user.user-detail');
        $response->assertViewHas('data', function ($data) {
            return $data['application'] !== null
                && $data['comment'] === '電車遅延のため'
                && $data['breaks'][0]['break_in'] === '12:30'
                && $data['breaks'][0]['break_out'] === '13:30';
        });
    }

    public function test_承認済みの詳細には元の勤怠が表示される()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser();
        $correction = $this->makeCorrection($user, '承認済み');

        $response = $this->actingAs($user)->get('/application/'.$correction->id);

        $response->assertViewHas('data', function ($data) {
            return $data['application'] === null
                && $data['clock_in'] === '09:00'
                && $data['breaks'][0]['break_in'] === '12:00'
                && $data['breaks'][0]['break_out'] === '13:00';
        });
    }

    public function test_他人の申請の詳細は見れない()
    {
        Carbon::setTestNow('2026-10-16 10:00:00');

        $user = $this->createUser();
        $correction = $this->makeCorrection($user, '承認待ち');
        $other = $this->createUser();

        $this->actingAs($other)
            ->get('/application/'.$correction->id)
            ->assertStatus(403);
    }
}
