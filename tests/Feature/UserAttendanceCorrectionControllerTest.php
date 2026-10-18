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

class UserAttendanceCorrectionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Attendance $attendance;
    private ProposalBreak $break;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 10:00:00');

        $this->user = User::factory()->create([
            'name'              => 'テスト太郎',
            'attendance_status' => '勤務外',
        ]);

        // 元の勤怠(9:00-18:00、休憩12:00-13:00)
        $this->attendance = Attendance::create([
            'user_id'      => $this->user->id,
            'work_date'    => '2026-10-16',
            'clock_in_at'  => '2026-10-16 09:00:00',
            'clock_out_at' => '2026-10-16 18:00:00',
        ]);

        $this->break = ProposalBreak::create([
            'attendance_id'  => $this->attendance->id,
            'break_start_at' => '2026-10-16 12:00:00',
            'break_end_at'   => '2026-10-16 13:00:00',
        ]);
    }

    private function asUser()
    {
        return $this->actingAs($this->user);
    }

    // 承認待ちの申請(10:00-19:00、休憩12:30-13:30)を作る
    private function makeCorrection(?User $owner = null, string $status = '承認待ち'): AttendanceCorrection
    {
        $correction = AttendanceCorrection::create([
            'user_id'         => ($owner ?? $this->user)->id,
            'attendance_id'   => $this->attendance->id,
            'clock_in_at'     => '2026-10-16 10:00:00',
            'clock_out_at'    => '2026-10-16 19:00:00',
            'approval_status' => $status,
            'comment'         => '電車遅延のため',
        ]);

        ProposalBreakCorrection::create([
            'attendance_correction_id' => $correction->id,
            'proposal_break_id'        => $this->break->id,
            'break_start_at'           => '2026-10-16 12:30:00',
            'break_end_at'             => '2026-10-16 13:30:00',
        ]);

        return $correction;
    }

    // 申請に送るデータ(変更あり)
    private function changedInput(array $override = []): array
    {
        return array_merge([
            'new_clock_in'  => '10:00',
            'new_clock_out' => '19:00',
            'new_break_in'  => ['12:30'],
            'new_break_out' => ['13:30'],
            'comment'       => '電車遅延のため',
        ], $override);
    }

    // ---------- 申請一覧 ----------

    public function test_申請一覧に自分の申請だけ表示される()
    {
        $this->makeCorrection();

        // 他人の申請(表示されてはいけない)
        $other = User::factory()->create();
        $this->makeCorrection($other);

        $response = $this->asUser()->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertViewIs('user.user-application-list');
        $response->assertViewHas('formattedApplications', function ($apps) {
            return $apps->count() === 1
                && $apps->first()['approval_status'] === '承認待ち'
                && $apps->first()['comment'] === '電車遅延のため';
        });
    }

    // ---------- 勤怠詳細(仕様の4項目) ----------

    public function test_詳細の名前がログインユーザーの氏名になっている()
    {
        $this->asUser()->get(route('attendance.detail', $this->attendance->id))
            ->assertStatus(200)
            ->assertSee('テスト太郎');
    }

    public function test_詳細の日付が選択した日付になっている()
    {
        $this->asUser()->get(route('attendance.detail', $this->attendance->id))
            ->assertViewHas('data', fn ($d) => $d['year'] === '2026 年'
                && str_starts_with($d['date'], '10/16'));
    }

    public function test_詳細の出勤退勤が打刻と一致している()
    {
        $this->asUser()->get(route('attendance.detail', $this->attendance->id))
            ->assertViewHas('data', fn ($d) => $d['clock_in'] === '09:00'
                && $d['clock_out'] === '18:00');
    }

    public function test_詳細の休憩が打刻と一致している()
    {
        $this->asUser()->get(route('attendance.detail', $this->attendance->id))
            ->assertViewHas('data', fn ($d) => $d['breaks'][0]['break_in'] === '12:00'
                && $d['breaks'][0]['break_out'] === '13:00');
    }

    public function test_申請がある場合は申請内容の休憩が表示される()
    {
        $this->makeCorrection();

        $this->asUser()->get(route('attendance.detail', $this->attendance->id))
            ->assertViewHas('data', fn ($d) => $d['application'] !== null
                && $d['comment'] === '電車遅延のため'
                && $d['breaks'][0]['break_in'] === '12:30'
                && $d['breaks'][0]['break_out'] === '13:30');
    }

    public function test_他人の勤怠詳細は見れない()
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('attendance.detail', $this->attendance->id))
            ->assertStatus(403);
    }

    // ---------- 修正申請(store) ----------

    public function test_修正を申請できる()
    {
        $response = $this->asUser()
            ->post('/attendance/' . $this->attendance->id, $this->changedInput());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('attendance.detail', $this->attendance->id));
        $response->assertSessionHas('success', '勤怠の修正を申請しました');

        $this->assertDatabaseHas('attendance_corrections', [
            'user_id'         => $this->user->id,
            'attendance_id'   => $this->attendance->id,
            'clock_in_at'     => '2026-10-16 10:00:00',
            'clock_out_at'    => '2026-10-16 19:00:00',
            'approval_status' => '承認待ち',
            'comment'         => '電車遅延のため',
        ]);

        $this->assertDatabaseHas('proposal_break_corrections', [
            'proposal_break_id' => $this->break->id,
            'break_start_at'    => '2026-10-16 12:30:00',
            'break_end_at'      => '2026-10-16 13:30:00',
        ]);

        // 元の勤怠は、申請の時点では変わらない
        $this->assertDatabaseHas('attendances', [
            'id'          => $this->attendance->id,
            'clock_in_at' => '2026-10-16 09:00:00',
        ]);
    }

    public function test_変更がない場合は申請できない()
    {
        $response = $this->asUser()->post('/attendance/' . $this->attendance->id, [
            'new_clock_in'  => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in'  => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment'       => 'そのまま',
        ]);

        $response->assertSessionHasErrors(['comment' => '変更がありません']);
        $this->assertDatabaseCount('attendance_corrections', 0);
    }

    public function test_他人の勤怠は申請できない()
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post('/attendance/' . $this->attendance->id, $this->changedInput())
            ->assertStatus(403);

        $this->assertDatabaseCount('attendance_corrections', 0);
    }
}