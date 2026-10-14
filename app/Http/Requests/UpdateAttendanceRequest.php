<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
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
    public function rules()
    {
        return [
            'work_date' => ['required', 'date_format:Y-m-d'],
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i', 'required_with:new_break_out.*'],
            'new_break_out' => ['nullable', 'array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i', 'required_with:new_break_in.*'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    // ③ エラーメッセージ
    public function messages()
    {
        return [
            'work_date.required' => '勤怠日を正しく指定してください',
            'work_date.date_format' => '勤怠日を正しく指定してください',

            'new_clock_in.required' => '出勤時刻を入力してください',
            'new_clock_in.date_format' => '日時の形式が正しくありません',
            'new_clock_out.required' => '退勤時刻を入力してください',
            'new_clock_out.date_format' => '日時の形式が正しくありません',

            'new_break_in.array' => '日時の形式が正しくありません',
            'new_break_in.*.date_format' => '日時の形式が正しくありません',
            'new_break_in.*.required_with' => '日時の形式が正しくありません',
            'new_break_out.array' => '日時の形式が正しくありません',
            'new_break_out.*.date_format' => '日時の形式が正しくありません',
            'new_break_out.*.required_with' => '日時の形式が正しくありません',

            'comment.required' => '修正理由を入力してください',
            'comment.string' => '修正理由を入力してください',
            'comment.max' => '修正理由は1000文字以内で入力してください',
        ];
    }

    // ④ 細かいチェック(基本のチェックが全部通ったあとに動く)
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            // 基本チェックでエラーがあれば、ここでは何もしない
            if ($validator->errors()->any()) {
                return;
            }

            $workDate = $this->work_date;
            $now = Carbon::now();

            // "09:00" に勤怠日をくっつけて、日時にする
            $clockIn = Carbon::parse($workDate.' '.$this->new_clock_in);
            $clockOut = Carbon::parse($workDate.' '.$this->new_clock_out);

            // 未来の時刻はダメ
            if ($clockIn->gt($now)) {
                $validator->errors()->add('new_clock_in', '未来の時刻は指定できません');
            }
            if ($clockOut->gt($now)) {
                $validator->errors()->add('new_clock_out', '未来の時刻は指定できません');
            }

            // 退勤は出勤より後(日付またぎもここで弾かれる)
            if ($clockOut->lte($clockIn)) {
                $validator->errors()->add('new_clock_out', '退勤時刻は出勤時刻より後にしてください');

                return;
            }

            // 休憩を1つずつチェック
            $breaks = [];
            $ins = $this->new_break_in ?? [];
            $outs = $this->new_break_out ?? [];

            foreach ($ins as $i => $in) {
                $out = $outs[$i] ?? null;

                // 開始も終了も空の行(追加用の空欄)は飛ばす
                if (blank($in) && blank($out)) {
                    continue;
                }

                $start = Carbon::parse($workDate.' '.$in);
                $end = Carbon::parse($workDate.' '.$out);

                // 未来の時刻はダメ
                if ($start->gt($now)) {
                    $validator->errors()->add("new_break_in.$i", '未来の時刻は指定できません');
                }
                if ($end->gt($now)) {
                    $validator->errors()->add("new_break_out.$i", '未来の時刻は指定できません');
                }

                // 休憩終了は開始より後
                if ($end->lte($start)) {
                    $validator->errors()->add("new_break_out.$i", '休憩終了時刻は休憩開始時刻より後にしてください');

                    continue;
                }

                // 休憩は勤務時間の中
                if ($start->lt($clockIn) || $end->gt($clockOut)) {
                    $validator->errors()->add("new_break_in.$i", '休憩時間は勤務時間内にしてください');

                    continue;
                }

                $breaks[$i] = ['start' => $start, 'end' => $end];
            }

            // 休憩どうしが重なっていないか(2つずつ比べる)
            foreach ($breaks as $i => $a) {
                foreach ($breaks as $j => $b) {
                    if ($i >= $j) {
                        continue; // 同じ組を2回比べない
                    }
                    if ($a['start']->lt($b['end']) && $b['start']->lt($a['end'])) {
                        $validator->errors()->add("new_break_in.$j", '休憩時間が重複しています');
                    }
                }
            }
        });
    }
}
