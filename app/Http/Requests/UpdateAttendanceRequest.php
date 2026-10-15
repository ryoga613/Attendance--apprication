<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAttendanceRequest extends FormRequest
{
    private const TIME_FORMAT = 'H:i';

    public function rules(): array
    {
        $time = 'date_format:'.self::TIME_FORMAT;

        return [
            'new_clock_in' => ['required', $time],
            'new_clock_out' => ['required', $time],
            'new_break_in' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable',  'required_with:new_break_out.*', $time],
            'new_break_out' => ['nullable', 'array'],
            'new_break_out.*' => ['nullable',  'required_with:new_break_in.*', $time],
            'comment' => ['required', 'string', 'max:1000'],
            'breaks.*.new_break_out' => ['required', $time],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // 基本的な形式チェックでエラーがある場合は相関チェックをスキップ
                if ($validator->errors()->any()) {
                    return;
                }

                $clockIn = Carbon::parse($this->new_clock_in);
                $clockOut = Carbon::parse($this->new_clock_out);

                if ($clockOut->lte($clockIn)) {
                    $validator->errors()->add('new_clock_out', '出勤時間が不適切な値です。');

                    return;
                }

                $breakIns = $this->new_break_in ?? [];
                $breakOuts = $this->new_break_out ?? [];

                // 比較用として整理済みの休憩時間リスト（[start, end]）を作成
                $intervals = [];

                foreach ($breakIns as $i => $breakIn) {
                    $breakOut = $breakOuts[$i] ?? null;

                    if (empty($breakIn) || empty($breakOut)) {
                        continue;
                    }

                    $bStart = Carbon::parse($breakIn);
                    $bEnd = Carbon::parse($breakOut);

                    // ② 休憩終了時刻は開始時刻より後
                    if ($bEnd->lte($bStart)) {
                        $validator->errors()->add("new_break_out.{$i}", '休憩時間または退勤時間が不適切な値です。');
                    }

                    // ③ 休憩は勤務時間内（出勤時刻以降かつ退勤時刻以前）
                    if ($bStart->lt($clockIn) || $bEnd->gt($clockOut)) {
                        $validator->errors()->add("new_break_in.{$i}", '休憩時間または退勤時間が不適切な値です。');
                    }

                    // ④ 休憩時間の重複禁止チェック
                    foreach ($intervals as $existing) {
                        // 時間帯が重なっているかどうかの判定 logic:
                        // (StartA < EndB) && (EndA > StartB)
                        if ($bStart->lt($existing['end']) && $bEnd->gt($existing['start'])) {
                            $validator->errors()->add("new_break_in.{$i}", '休憩時間が重複しています。');
                            break;
                        }
                    }

                    $intervals[] = [
                        'start' => $bStart,
                        'end' => $bEnd,
                    ];
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください。',
            'new_clock_in.date_format' => '出勤時間の形式が正しくありません。',
            'new_clock_out.required' => '退勤時間を入力してください。',
            'new_clock_out.date_format' => '退勤時間の形式が正しくありません。',
            'new_clock_out.after' => '休憩時間または退勤時間が不適切な値です。',

            'new_break_in.*.required_with' => '休憩開始時間を入力してください。',
            'new_break_in.array' => '日時の形式が正しくありません',
            'new_break_in.*.date_format' => '日時の形式が正しくありません',

            'new_break_out.*.required_with' => '休憩終了時間を入力してください。',
            'new_break_out.array' => '日時の形式が正しくありません',
            'new_break_out.*.date_format' => '日時の形式が正しくありません',

            'comment.required' => '備考を入力してください。',
            'comment.string' => '備考を入力してください',
            'comment.max' => '備考は1000文字以内で入力してください',
        ];
    }
}
