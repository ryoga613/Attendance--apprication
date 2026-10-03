<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionRequest extends FormRequest
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
            // 'new_date' => 'required|date_format:Y-m-d',

            'new_clock_in' => 'required|date_format:H:i',
            'new_clock_out' => 'required|date_format:H:i',
            'new_break_in.*' => 'nullable|date_format:H:i',
            'new_break_out.*' => 'nullable|date_format:H:i',
            'comment' => 'required|string|max:1000',
        ];
    }

    public function messages()
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_in.date_format' => '出勤時間は「09:00」の形式で入力してください',

            'new_clock_out.required' => '退勤時間を入力してください',
            'new_clock_out.date_format' => '退勤時間は「18:00」の形式で入力してください',

            'new_break_in.date_format' => '休憩開始時間は「12:00」の形式で入力してください',
            'new_break_out.date_format' => '休憩終了時間は「13:00」の形式で入力してください',

            'comment.required' => '備考を記入してください',
            'comment.string' => '備考は文字で入力してください',
            'comment.max' => '備考は1000文字以内で入力してください',
        ];
    }
}
