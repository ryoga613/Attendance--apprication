<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiLoginTest extends TestCase
{
    use RefreshDatabase;

    // ログインしてトークンを取得する
    private function login(User $user): string
    {
        return $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
            ->json('token');
    }

    // トークンを付けてAPIを呼び、ステータスコードを返す
    private function requestWithToken(string $token): int
    {
        // 前のリクエストのログイン状態が残らないようにする
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/attendance-records')->getStatusCode();
    }

    // ---------- トークンの有効期限 ----------

    public function test_ログインで発行したトークンには24時間後の有効期限が記録される(): void
    {
        $this->travelTo('2026-11-03 10:00:00');
        $user = User::factory()->create(['password' => 'password123']);

        $this->login($user);

        $this->assertSame('2026-11-04 10:00:00', PersonalAccessToken::first()->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_トークンは24時間以内なら使える(): void
    {
        $this->travelTo('2026-11-03 10:00:00');
        $user = User::factory()->create(['password' => 'password123']);
        $token = $this->login($user);

        $this->travelTo('2026-11-04 09:59:00');

        $this->assertSame(200, $this->requestWithToken($token));
    }

    public function test_トークンは24時間を過ぎると使えない(): void
    {
        $this->travelTo('2026-11-03 10:00:00');
        $user = User::factory()->create(['password' => 'password123']);
        $token = $this->login($user);

        $this->travelTo('2026-11-04 10:01:00');

        $this->assertSame(401, $this->requestWithToken($token));
    }

    // ---------- 期限切れトークンの削除 ----------

    public function test_期限切れから24時間たったトークンは削除コマンドで消える(): void
    {
        $user = User::factory()->create();

        // 作成から47時間(期限切れから23時間)と、49時間(期限切れから25時間)のトークン
        $this->travelTo('2026-11-08 01:00:00');
        $user->createToken('期限切れから23時間', ['*'], now()->addMinutes(config('sanctum.expiration')));
        $this->travelTo('2026-11-07 23:00:00');
        $user->createToken('期限切れから25時間', ['*'], now()->addMinutes(config('sanctum.expiration')));

        $this->travelTo('2026-11-10 00:00:00');
        $this->artisan('sanctum:prune-expired --hours=24')->assertSuccessful();

        $this->assertDatabaseHas('personal_access_tokens', ['name' => '期限切れから23時間']);
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => '期限切れから25時間']);
    }

    public function test_期限切れトークンの削除が1時間ごとに予約されている(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'sanctum:prune-expired --hours=24'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }

    public function test_正しい情報でトークンが取得できる(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_ログインに続けて失敗すると試行回数が制限される(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
