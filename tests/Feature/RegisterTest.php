<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    // 正しい入力データ。テストごとに一部だけ上書きする
    private function validData(array $override = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $override);
    }

    public function test_メールアドレスが未入力だとエラーになる()
    {
        $response = $this->post('/register', $this->validData(['email' => '']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください。']);
    }

    public function test_パスワードが8文字未満だとエラーになる()
    {
        $response = $this->post('/register', $this->validData([
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください。']);
    }

    public function test_パスワードが一致しないとエラーになる()
    {
        $response = $this->post('/register', $this->validData([
            'password_confirmation' => 'different123',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードと一致しません。']);
    }

    public function test_パスワードが未入力だとエラーになる()
    {
        $response = $this->post('/register', $this->validData([
            'password' => '',
            'password_confirmation' => '',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください。']);
    }

    // 失敗のテストすべてに追加する(例: 名前未入力)
    public function test_名前が未入力だとエラーになる()
    {
        $response = $this->post('/register', $this->validData(['name' => '']));

        $response->assertSessionHasErrors(['name' => 'お名前を入力してください。']);
    }

    // 成功のテストを強化
    public function test_正しく入力すると登録される()
    {
        $this->post('/register', $this->validData());

        $this->assertDatabaseHas('users', [
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
        ]);

        // パスワードが平文ではなく、ハッシュ化されている
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    // 境界値: ちょうど8文字なら通る
    public function test_パスワードが8文字ちょうどなら登録できる()
    {
        $this->post('/register', $this->validData([
            'password' => 'pass1234',
            'password_confirmation' => 'pass1234',
        ]));

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
