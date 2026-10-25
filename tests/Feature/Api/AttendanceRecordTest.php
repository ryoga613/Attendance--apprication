<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAttendanceRecordTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    public function test_apiで勤怠一覧に自分の勤怠だけが表示される(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // 自分の勤怠
        $attendance1 = Attendance::factory()->create([
            'user_id' => $user1->id,
            'work_date' => '2026-10-19',
        ]);

        // 他人の勤怠（同じ日付）
        $attendance2 = Attendance::factory()->create([
            'user_id' => $user2->id,
            'work_date' => '2026-10-19',
        ]);

        // user1としてログインし、日付を指定して取得
        $response = $this->actingAs($user1)
            ->getJson('/api/v1/attendance-records?date=2026-10-19&per_page=20');

        $response->assertOk()
            ->assertJsonFragment(['id' => $attendance1->id])
            ->assertJsonMissing(['id' => $attendance2->id]);
    }

    public function test_ap_iで勤怠の確認が詳細取得ができる(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-19',
        ]);

        $response = $this->actingAs($user)->get("/api/v1/attendance-records/{$attendance->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $attendance->id]);
    }

    public function test_存在しない_i_dでは404とエラー_jso_nが返る()
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-19',
        ]);

        $response = $this->get('/api/v1/attendance-records/99999');

        // $response->dump();
        $response->assertStatus(404)
            ->assertJson([
                'message' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    public function test_ap_iで勤怠の登録ができる()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response1 = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response1->assertOk()
            ->assertJsonStructure(['token']); // トークン取得

        $workDate = now()->subDay();

        $response2 = $this->actingAs($user)->post('api/v1/attendance-records', [
            // 'user_id'      => $user->id,
            'work_date' => $workDate->toDateString(),
            'clock_in_at' => $workDate->copy()->setTime(9, 0)->toDateTimeString(),
            'clock_out_at' => $workDate->copy()->setTime(18, 0)->toDateTimeString(),
            'comment' => 'テスト登録',
        ]);

        $response2->assertStatus(201)
            ->assertJsonFragment(['user_id' => $user->id, 'comment' => 'テスト登録']);

        // DBに保存されているか
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'comment' => 'テスト登録',
        ]);
    }

    public function test_ap_iで勤怠の編集ができる(): void
    {

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-19',
            'clock_in_at' => '2026-10-19 09:00:00',   // 勤務中の状態
        ]);

        // 1. ログインしてトークンを取得する
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginResponse->assertOk()->assertJsonStructure(['token']);

        $token = $loginResponse->json('token');

        // 2. トークンを付けて、退勤時刻を追加する（PUT）
        $response = $this->withToken($token)->putJson("/api/v1/attendance-records/{$attendance->id}", [
            'clock_out_at' => '2026-10-19 18:00:00',
            'comment' => '退勤を追加',
        ]);

        // 3. 結果を確認する
        $response->assertOk();

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'work_date' => '2026-10-19',
            'clock_in_at' => '2026-10-19 09:00:00',
            'clock_out_at' => '2026-10-19 18:00:00',
            'comment' => '退勤を追加',
        ]);
    }

    public function test_バリデーションエラー時に422と日本語エラーメッセージが返る()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // 必須項目を送らずに登録リクエストを実行
        $response = $this->withToken($token)
            ->postJson('/api/v1/attendance-records', []);

        // 422と日本語エラーメッセージを確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'work_date',
            ]);
    }

    public function test_ap_iで勤怠を削除できる(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-19',
            'clock_in_at' => '2026-10-19 09:00:00',
            'clock_out_at' => '2026-10-19 18:00:00',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
        ]); // 勤怠データは存在する

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginResponse->assertOk()->assertJsonStructure(['token']);

        $token = $loginResponse->json('token');

        $response = $this->withToken($token)->deleteJson("/api/v1/attendance-records/{$attendance->id}");

        $response->assertStatus(204);

    }
}
