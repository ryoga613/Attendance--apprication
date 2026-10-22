<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    // 本人チェックはコントローラーのPolicyで行うのでtrue
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'work_date' => ['nullable', 'date', Rule::unique('attendances', 'work_date')
                ->where('user_id', $this->user()->id)
                ->ignore($this->route('attendanceRecord'))],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [

            'work_date.date' => '勤務日は正しい日付を入力してください。',
            'work_date.unique' => 'この勤務日はすでに登録されています。',

            'clock_in_at.date' => '出勤時刻は正しい日時を入力してください。',

            'clock_out_at.date' => '退勤時刻は正しい日時を入力してください。',
            'clock_out_at.after' => '退勤時刻は出勤時刻より後の時間を入力してください。',

            'comment.string' => '備考は文字列で入力してください。',
            'comment.max' => '備考は1000文字以内で入力してください。',
        ];
    }
}
