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

class AdminAttendanceCorrectionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Attendance $attendance;

    private ProposalBreak $break;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 10:00:00');

        $this->admin = User::factory()->create(['admin_status' => true]);
        $this->staff = User::factory()->create(['name' => 'スタッフ太郎']);

        // 元の勤怠(9:00-18:00、休憩12:00-13:00)
        $this->attendance = Attendance::create([
            'user_id' => $this->staff->id,
            'work_date' => '2026-10-16',
            'clock_in_at' => '2026-10-16 09:00:00',
            'clock_out_at' => '2026-10-16 18:00:00',
        ]);

        $this->break = ProposalBreak::create([
            'attendance_id' => $this->attendance->id,
            'break_start_at' => '2026-10-16 12:00:00',
            'break_end_at' => '2026-10-16 13:00:00',
        ]);
    }

    // 承認待ちの修正申請(10:00-19:00、休憩12:30-13:30)を作る
    private function makeCorrection(): AttendanceCorrection
    {
        $correction = AttendanceCorrection::create([
            'user_id' => $this->staff->id,
            'attendance_id' => $this->attendance->id,
            'clock_in_at' => '2026-10-16 10:00:00',
            'clock_out_at' => '2026-10-16 19:00:00',
            'approval_status' => '承認待ち',
            'comment' => '電車遅延のため',
        ]);

        ProposalBreakCorrection::create([
            'attendance_correction_id' => $correction->id,
            'proposal_break_id' => $this->break->id,
            'break_start_at' => '2026-10-16 12:30:00',
            'break_end_at' => '2026-10-16 13:30:00',
        ]);

        return $correction;
    }

    private function asAdmin()
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /** 申請一覧が見れる */
    public function test_申請一覧が見れる()
    {
        $this->makeCorrection();

        $response = $this->asAdmin()->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertViewIs('admin.admin-application-list');
        $response->assertViewHas('formattedApplications', function ($apps) {
            return $apps->count() === 1
                && $apps->first()['approval_status'] === '承認待ち'
                && $apps->first()['comment'] === '電車遅延のため';
        });
    }

    /** 管理者が勤怠を直接修正できる(store) */
    public function test_管理者が勤怠を直接修正できる()
    {
        $response = $this->asAdmin()->post("/admin/attendance/{$this->attendance->id}", [
            'new_clock_in' => '15:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['16:30'],
            'new_break_out' => ['17:30'],
            'comment' => '管理者による修正',
        ]);

        $response->assertRedirect(route('admin.attendance.list'));
        $response->assertSessionHas('success', '勤怠の修正をしました');

        $this->assertDatabaseHas('attendances', [
            'id' => $this->attendance->id,
            'clock_in_at' => '2026-10-16 15:00:00',
            'clock_out_at' => '2026-10-16 18:00:00',
        ]);

        $this->assertDatabaseHas('proposal_breaks', [
            'id' => $this->break->id,
            'break_start_at' => '2026-10-16 16:30:00',
            'break_end_at' => '2026-10-16 17:30:00',
        ]);

        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_id' => $this->attendance->id,
            'approval_status' => '承認済み',
            'comment' => '管理者による修正',
        ]);
    }

    /** コメントが空だと修正できない */
    public function test_コメントなしだと修正できない()
    {
        $response = $this->asAdmin()->post('/attendance/'.$this->attendance->id, [
            'new_clock_in' => '10:00',
            'new_clock_out' => '19:00',
            'comment' => '',
        ]);

        $response->assertSessionHasErrors('comment');

        // 勤怠は変わっていない
        $this->assertDatabaseHas('attendances', [
            'id' => $this->attendance->id,
            'clock_in_at' => '2026-10-16 09:00:00',
        ]);
    }

    /** 申請の詳細画面が見れる */
    public function test_申請の詳細画面が見れる()
    {
        $correction = $this->makeCorrection();

        $response = $this->asAdmin()->get('/stamp_correction_request/approve/'.$correction->id);

        $response->assertStatus(200);
        $response->assertViewIs('admin.admin-application-detail');
        $response->assertViewHas('application', fn ($a) => $a->id === $correction->id);
    }

    /** 申請を承認すると、勤怠に反映されて承認済みになる */
    public function test_申請を承認すると勤怠に反映される()
    {
        $correction = $this->makeCorrection();

        $response = $this->asAdmin()->post('/stamp_correction_request/approve/'.$correction->id);

        $response->assertRedirect();
        $response->assertSessionHas('success', '勤怠修正申請を承認しました');

        // 勤怠が申請内容になっている
        $this->assertDatabaseHas('attendances', [
            'id' => $this->attendance->id,
            'clock_in_at' => '2026-10-16 10:00:00',
            'clock_out_at' => '2026-10-16 19:00:00',
        ]);

        // 休憩が申請内容になっている
        $this->assertDatabaseHas('proposal_breaks', [
            'break_start_at' => '2026-10-16 12:30:00',
            'break_end_at' => '2026-10-16 13:30:00',
        ]);

        // 申請が承認済みになっている
        $this->assertDatabaseHas('attendance_corrections', [
            'id' => $correction->id,
            'approval_status' => '承認済み',
        ]);
    }

    /** 承認すると、既存の休憩が更新され、重複して作られない */
    public function test_承認すると既存の休憩が更新され重複しない()
    {
        $correction = $this->makeCorrection();

        // 休憩IDをProposalBreakCorrectionのIDと確実にずらす
        $breakCorrection = $correction->proposalBreaks->first();
        $this->break->delete();
        $this->break = ProposalBreak::forceCreate([
            'id' => $breakCorrection->id + 1000,
            'attendance_id' => $this->attendance->id,
            'break_start_at' => '2026-10-16 12:00:00',
            'break_end_at' => '2026-10-16 13:00:00',
        ]);
        ProposalBreakCorrection::forceCreate([
            'attendance_correction_id' => $correction->id,
            'proposal_break_id' => $this->break->id,
            'break_start_at' => '2026-10-16 12:30:00',
            'break_end_at' => '2026-10-16 13:30:00',
        ]);

        $this->asAdmin()->post('/stamp_correction_request/approve/'.$correction->id);

        $this->assertSame(1, $this->attendance->breaks()->count());
        $this->assertDatabaseHas('proposal_breaks', [
            'id' => $this->break->id,
            'break_start_at' => '2026-10-16 12:30:00',
            'break_end_at' => '2026-10-16 13:30:00',
        ]);
    }

    /** 申請で追加された休憩は作成され、申請にない休憩は削除される */
    public function test_承認すると休憩が申請内容と一致する()
    {
        $removedBreak = ProposalBreak::create([
            'attendance_id' => $this->attendance->id,
            'break_start_at' => '2026-10-16 16:00:00',
            'break_end_at' => '2026-10-16 16:15:00',
        ]);

        $correction = $this->makeCorrection();
        ProposalBreakCorrection::create([
            'attendance_correction_id' => $correction->id,
            'proposal_break_id' => null,
            'break_start_at' => '2026-10-16 17:00:00',
            'break_end_at' => '2026-10-16 17:30:00',
        ]);

        $this->asAdmin()->post('/stamp_correction_request/approve/'.$correction->id);

        $this->assertSame(2, $this->attendance->breaks()->count());
        $this->assertDatabaseMissing('proposal_breaks', ['id' => $removedBreak->id]);
        $this->assertDatabaseHas('proposal_breaks', [
            'attendance_id' => $this->attendance->id,
            'break_start_at' => '2026-10-16 17:00:00',
            'break_end_at' => '2026-10-16 17:30:00',
        ]);
    }

    /** 承認済みの申請は再度承認できない */
    public function test_承認済みの申請は再度承認できない()
    {
        $correction = $this->makeCorrection();
        $correction->update(['approval_status' => '承認済み']);

        $response = $this->asAdmin()->post('/stamp_correction_request/approve/'.$correction->id);

        $response->assertSessionHasErrors('approval_status');
        $this->assertDatabaseHas('attendances', [
            'id' => $this->attendance->id,
            'clock_in_at' => '2026-10-16 09:00:00',
        ]);
    }

    /** 未ログインでは申請の詳細画面が見れない */
    public function test_未ログインでは申請の詳細画面が見れない()
    {
        $correction = $this->makeCorrection();

        $response = $this->get('/stamp_correction_request/approve/'.$correction->id);

        $response->assertRedirect(route('login'));
    }

    /** 一般ユーザーは申請の詳細画面(承認画面)が見れない */
    public function test_一般ユーザーは申請の承認画面が見れない()
    {
        $correction = $this->makeCorrection();

        $response = $this->actingAs($this->staff)->get('/stamp_correction_request/approve/'.$correction->id);

        $response->assertRedirect(route('login'));
    }
}
