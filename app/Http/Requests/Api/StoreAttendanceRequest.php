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
            'work_date' => [
                'required',
                'date',
                'before_or_equal:today',
                Rule::unique('attendances', 'work_date')
                    ->where('user_id', $this->user()->id),
            ],
            'clock_in_at' => ['required', 'date', 'before_or_equal:now'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at', 'before_or_equal:now'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    // 任意：エラーメッセージを日本語にしたい場合
    public function messages(): array
    {
        return [
            'work_date.required' => '勤務日を入力してください。',
            'work_date.date' => '勤務日の形式が正しくありません。',
            'work_date.before_or_equal' => '未来の日付は登録できません。',
            'work_date.unique' => 'この日付の勤怠はすでに登録されています。',

            'clock_in_at.required' => '出勤時間を入力してください。',
            'clock_in_at.date' => '出勤時間の形式が正しくありません。',
            'clock_in_at.before_or_equal' => '出勤時間に未来の時刻は指定できません。',

            'clock_out_at.date' => '退勤時間の形式が正しくありません。',
            'clock_out_at.after' => '退勤時間は出勤時間より後にしてください。',
            'clock_out_at.before_or_equal' => '退勤時間に未来の時刻は指定できません。',

            'comment.string' => '備考は文字列で入力してください。',
            'comment.max' => '備考は255文字以内で入力してください。',
        ];
    }
}
