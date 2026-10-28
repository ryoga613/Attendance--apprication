<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'admin_status' => true,
        ]);
    }

    public function test_メールアドレスが未入力だとエラーになる()
    {
        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください。']);
    }

    public function test_パスワードが未入力だとエラーになる()
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください。']);
    }

    public function test_登録内容と一致しないとエラーになる()
    {
        $response = $this->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません。']);
    }

    public function test_正しい情報で管理者がログインできる()
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticated('admin');   // adminガードでログイン
    }

    public function test_一般ユーザーは管理者としてログインできない()
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
            'admin_status' => false,
        ]);

        $this->post('/admin/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertGuest('admin');
    }

    public function test_管理者がログアウトするとセッションが破棄される()
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $this->actingAs($admin, 'admin')->withSession(['keep' => 'value']);

        $response = $this->post('/admin/logout');

        $response->assertRedirect('/admin/login');
        $response->assertSessionMissing('keep');
        $this->assertGuest('admin');
    }

    public function test_管理者権限を外されたユーザーは管理画面に入れない()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin, 'admin');

        $admin->update(['admin_status' => false]);

        $this->get('/admin/attendance/list')->assertStatus(403);
    }

    public function test_管理者ログインに続けて失敗すると試行回数が制限される()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
        }

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
