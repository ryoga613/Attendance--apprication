<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\ProposalBreakCorrection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceCorrectionController extends Controller
{
    public function store(AttendanceCorrectionRequest $request, $id)
    {

        $user = Auth::user();
        $attendance = Attendance::findOrFail($id);
        $date = Carbon::parse($attendance->work_date)->toDateString();
        $breaks = $attendance->proposalBreaks->sortBy('break_start_at')->values();

        $originalBreaks = $breaks
            ->filter(fn ($b) => $b->break_start_at && $b->break_end_at)
            ->map(fn ($b) => $b->break_start_at->format('H:i').'-'.$b->break_end_at->format('H:i'))
            ->values()
            ->all();

        $newBreaks = collect($request->new_break_in)
            ->map(fn ($in, $i) => [$in, $request->new_break_out[$i] ?? null])
            ->filter(fn ($pair) => $pair[0] && $pair[1])
            ->map(fn ($pair) => Carbon::parse($pair[0])->format('H:i').'-'.Carbon::parse($pair[1])->format('H:i'))
            ->values()
            ->all();

        $noChange = $attendance->clock_in_at?->format('H:i') === Carbon::parse($request->new_clock_in)->format('H:i')
            && $attendance->clock_out_at?->format('H:i') === Carbon::parse($request->new_clock_out)->format('H:i')
            && $originalBreaks === $newBreaks;

        if ($noChange) {
            return redirect()
                ->route('attendance.show', $id)
                ->with('error', '変更がありません');
        }

        // 1. 勤怠の修正申請を、1回だけ作る
        $correction = ([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'clock_in_at' => $date.' '.$request->new_clock_in,
            'clock_out_at' => $date.' '.$request->new_clock_out,
            'approval_status' => '承認待ち',
            'comment' => $request->comment,
        ]);

        $correction = AttendanceCorrection::create($correction);

        foreach ($request->new_break_in as $i => $breakIn) {
            $breakOut = $request->new_break_out[$i];

            if (! $breakIn || ! $breakOut) {
                continue;
            }

            $breakRequest = ([
                'attendance_correction_id' => $correction->id,
                'proposal_break_id' => $breaks[$i]->id ?? null,
                'clock_in_at' => $date.' '.$request->new_clock_in,
                'clock_out_at' => $date.' '.$request->new_clock_out,
                'break_start_at' => $date.' '.$breakIn,
                'break_end_at' => $date.' '.$breakOut,
            ]);

            ProposalBreakCorrection::create($breakRequest);
        }

        return redirect()->route('attendance.list')->with('success', '勤怠の修正を申請しました');
    }
}
