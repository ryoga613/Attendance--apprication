<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ProposalBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    // 勤務登録画面
    public function test_user_can_see_attendance()
    {
        $user = User::create([
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->markEmailAsVerified();
        $before = now()->startOfSecond();
        $response = $this->actingAs($user)->get('/attendance');

        $after = now();
        $response->assertStatus(200);
        $response->assertViewIs('user.attendance-register');
        $response->assertViewHas('formattedTime', function ($time) use ($before, $after) {
            return $time->between($before, $after);
        });
        $response->assertViewHas('user', fn ($u) => $u->attendance_status === '勤務外');
        $response->assertViewHas('todayAttendance', $user->Attendance);
        $response->assertViewHas('formattedDate', function ($time) use ($before, $after) {
            return $time->between($before, $after);
        });
    }

    // 勤務登録（出勤時）
    public function test_attendance_status_is_correct_when_off_work()
    {
        $user = User::create([
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'attendance_status' => '勤務外',
        ]);
        $user->markEmailAsVerified();
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    // 勤務登録（休憩入時）
    public function test_attendance_status_is_correct_when_working()
    {
        $user = User::create([
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'attendance_status' => '出勤中',
        ]);
        $user->markEmailAsVerified();
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤中');

    }

    // 勤務登録（休憩出時）
    public function test_attendance_status_is_correct_when_on_break()
    {
        $user = User::create([
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'attendance_status' => '休憩中',
        ]);
        $user->markEmailAsVerified();
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    // 勤務登録（退勤後）
    public function test_attendance_status_is_correct_when_clocked_out()
    {
        $user = User::create([
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'attendance_status' => '退勤後',
        ]);
        $user->markEmailAsVerified();
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤後');

    }

    // 勤務一覧画面
    public function test_user_can_see_attendance_list(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->startOfMonth()->subMonth();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => $thisMonth->toDateString(),
            'clock_in_at' => $thisMonth->copy()->setTime(9, 0),
            'clock_out_at' => $thisMonth->copy()->setTime(18, 0),
        ]);
        ProposalBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => $thisMonth->copy()->setTime(12, 0),
            'break_end_at' => $thisMonth->copy()->setTime(13, 0),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $lastMonth->toDateString(),
            'clock_in_at' => $lastMonth->copy()->setTime(9, 0),
            'clock_out_at' => $lastMonth->copy()->setTime(18, 0),
        ]);
        Attendance::create([
            'user_id' => $other->id,
            'work_date' => $thisMonth->toDateString(),
            'clock_in_at' => $thisMonth->copy()->setTime(9, 0),
            'clock_out_at' => $thisMonth->copy()->setTime(18, 0),
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertOk()
            ->assertViewHas('previousMonth', $lastMonth->format('Y-m'))
            ->assertViewHas('nextMonth', $thisMonth->copy()->addMonth()->format('Y-m'))
            ->assertViewHas('date', fn ($date) => $date->format('Y-m') === $thisMonth->format('Y-m'))
            ->assertViewHas('formattedAttendanceRecords', function ($records) use ($attendance, $thisMonth) {
                return $records->count() === 1
                    && $records->first() === [
                        'id' => $attendance->id,
                        'date' => $thisMonth->copy()->locale('ja')->isoFormat('MM/DD(ddd)'),
                        'clock_in' => '09:00',
                        'clock_out' => '18:00',
                        'total_break_time' => '01:00:00',
                        'total_time' => '08:00:00',
                    ];
            });
    }

    // 勤務登録機能(出勤)
    public function test_user_can_store_action_is_clock_in(): void
    {
        $user = User::factory()->create(['attendance_status' => '勤務外']);

        $response = $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $response->assertRedirect(route('attendance.register.form'));
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
        ]);
        $this->assertSame('出勤中', $user->fresh()->attendance_status);
    }

    public function test_user_cant_attend_works_twice_in_a_day(): void
    {
        $user = User::factory()->create(['attendance_status' => '勤務外']);

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }

    // 勤務登録機能(休憩)
    public function test_user_can_store_action_is_break_in(): void
    {
        $user = User::factory()->create(['attendance_status' => '出勤中']);
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => today()->toDateString(),
            'clock_in_at' => now(),
            'clock_out_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        $response->assertRedirect(route('attendance.register.form'));
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
        ]);
        $this->assertSame('休憩中', $user->fresh()->attendance_status);
    }

    public function test_user_can_break_in_twice_in_a_day(): void
    {
        $user = User::factory()->create(['attendance_status' => '出勤中']);
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => today()->toDateString(),
            'clock_in_at' => now(),
            'clock_out_at' => null,
        ]);

        foreach (range(1, 2) as $i) {
            $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
            $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);
        }

        $this->assertSame(2, ProposalBreak::where('attendance_id', $attendance->id)->count());
        $this->assertSame('出勤中', $user->fresh()->attendance_status);
    }

    // 勤務登録機能(休憩出)
    public function test_user_can_store_action_is_break_out(): void
    {
        $user = User::factory()->create(['attendance_status' => '休憩中']);
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => today()->toDateString(),
            'clock_in_at' => now(),
            'clock_out_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        $response->assertRedirect(route('attendance.register.form'));
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
        ]);
        $this->assertSame('出勤中', $user->fresh()->attendance_status);
    }

    // 勤務登録機能(退勤)
    public function test_user_can_store_action_is_clock_out(): void
    {
        $user = User::factory()->create(['attendance_status' => '出勤中']);
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => today()->toDateString(),
            'clock_in_at' => now(),
            'clock_out_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/attendance', ['action' => 'clock_out']);

        $response->assertRedirect(route('attendance.register.form'));
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
        ]);
        $this->assertSame('退勤後', $user->fresh()->attendance_status);
    }
}
