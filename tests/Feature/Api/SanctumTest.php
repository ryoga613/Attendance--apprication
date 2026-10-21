<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    private function makeAttendance(User $user): Attendance
    {
        $date = now()->subDays(2);

        return Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $date->toDateString(),
            'clock_in_at' => $date->copy()->setTime(9, 0),
            'clock_out_at' => null,
        ]);
    }

    /**
     * 更新用の正しいリクエストボディ
     */
    private function validBody(Attendance $attendance): array
    {
        $date = $attendance->work_date;

        return [
            'work_date' => $date->toDateString(),
            'clock_in_at' => $date->copy()->setTime(9, 0)->toDateTimeString(),
            'clock_out_at' => $date->copy()->setTime(18, 0)->toDateTimeString(),
            'comment' => '更新テスト',
        ];
    }

    // ------------------------------------------------------------
    // 未認証時に書き込み系APIで401が返る
    // ------------------------------------------------------------

    public function test_未認証で_postすると401が返る(): void
    {
        $this->postJson('/api/v1/attendance-records', [])
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_未認証で_putすると401が返る(): void
    {
        $attendance = $this->makeAttendance(User::factory()->create());

        $this->putJson("/api/v1/attendance-records/{$attendance->id}", $this->validBody($attendance))
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        // 更新されていないこと
        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
            'comment' => '更新テスト',
        ]);
    }

    public function test_未認証で_deleteすると401が返る(): void
    {
        $attendance = $this->makeAttendance(User::factory()->create());

        $this->deleteJson("/api/v1/attendance-records/{$attendance->id}")
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        // 削除されていないこと
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
    }

    // ------------------------------------------------------------
    // 認証済みユーザーは自分の勤怠を更新・削除できる
    // ------------------------------------------------------------

    public function test_本人は自分の勤怠を更新できる(): void
    {
        $user = User::factory()->create();
        $attendance = $this->makeAttendance($user);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/attendance-records/{$attendance->id}", $this->validBody($attendance))
            ->assertOk();

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'comment' => '更新テスト',
        ]);
        $this->assertNotNull($attendance->fresh()->clock_out_at);
    }

    public function test_本人は自分の勤怠を削除できる(): void
    {
        $user = User::factory()->create();
        $attendance = $this->makeAttendance($user);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/attendance-records/{$attendance->id}")
            ->assertNoContent(); // 204

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    // ------------------------------------------------------------
    // 他ユーザーの勤怠を更新・削除しようとすると403が返る
    // ------------------------------------------------------------

    public function test_他人の勤怠は更新できない(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $attendance = $this->makeAttendance($owner);
        Sanctum::actingAs($other);

        $response = $this->putJson("/api/v1/attendance-records/{$attendance->id}", $this->validBody($attendance))
            ->assertStatus(403)
            ->assertJson(['error' => 'この操作を実行する権限がありません。']);

        // 更新されていないこと
        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
            'comment' => '更新テスト',
        ]);
    }

    public function test_他人の勤怠は削除できない(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $attendance = $this->makeAttendance($owner);
        Sanctum::actingAs($other);

        $this->deleteJson("/api/v1/attendance-records/{$attendance->id}")
            ->assertStatus(403)
            ->assertJson(['error' => 'この操作を実行する権限がありません。']);

        // 削除されていないこと
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
    }
}
