<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'password' =>'required|string'
        ];
    }

    public function messages()
    {
        return [
            'email.required' =>'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスの形式が正しくありません。',
            'email.max' =>'メールアドレスは２５５文字以内で入力してください。',
            'password.required'=> 'パスワードを入力してください。',
            'password.string' => 'パスワードの形式が正しくありません。'
        ];
    }
}
