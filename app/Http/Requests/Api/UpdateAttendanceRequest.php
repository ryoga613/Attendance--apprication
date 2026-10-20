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
            'work_date' => ['nullable', 'date',Rule::unique('attendances', 'work_date')
                ->where('user_id', $this->user()->id),],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            // 'work_date.required' => '勤務日を入力してください。',
            // 'clock_in_at.required' => '出勤時間を入力してください。',
            'clock_out_at.after' => '退勤時間は出勤時間より後にしてください。',
            'clock_out_at.required' => '待機イン時刻を入力してください。',
            'comment.max' => '備考は1000文字以内で入力してください。',
        ];
    }
}
