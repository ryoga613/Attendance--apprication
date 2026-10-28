<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLoginTest extends TestCase
{
    use RefreshDatabase;

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
