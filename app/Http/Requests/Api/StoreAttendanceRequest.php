<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class StoreAttendanceRequest extends FormRequest
{
    // ログイン確認はルート側のauth:sanctumでやっているので、ここはtrueでOK
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'work_date' => 'required|date' ,
            Rule::unique('attendances', 'work_date')
                ->where('user_id', $this->user()->id),
            'clock_in_at' => 'required|date',
            'clock_out_at' => 'nullable|date|after:clock_in_at',
            'comment' => 'nullable|string|max:255',
        ];
    }

    // 任意：エラーメッセージを日本語にしたい場合
    public function messages(): array
    {
        return [
            'work_date.required' => '勤務日を入力してください。',
            'work_date.unique' => 'この日付の勤怠はすでに登録されています。',

            'clock_in_at.required' => '出勤時刻を入力してください。',
            'clock_out_at.after' => '退勤時刻か出勤時刻が不適切な値です。',
        ];
    }
}
