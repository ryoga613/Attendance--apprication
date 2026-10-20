<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAttendanceRequest;
use App\Http\Requests\Api\UpdateAttendanceRequest;
use App\Models\Attendance;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AttendanceController extends Controller
{
    use AuthorizesRequests;

    // 一覧を取得する
    public function index()
    {
        $attendances = Attendance::orderByDesc('work_date')->get();

        return response()->json($attendances);
    }

    // 詳細を取得する
    public function show(Attendance $attendanceRecord)
    {
        return response()->json($attendanceRecord);
    }

    // 新規登録する
    public function store(StoreAttendanceRequest $request)
    {
        $validated = $request->validated();

        // user_id はログイン中のユーザーから自動で入れる
        $validated['user_id'] = $request->user()->id;

        $attendance = Attendance::create($validated);

        return response()->json($attendance, 201);
    }

    // 更新する
    public function update(UpdateAttendanceRequest $request, Attendance $attendanceRecord)
    {
        // 本人かどうかをPolicyでチェック（違えば403）
        $this->authorize('apiUpdate', $attendanceRecord);

        $validated = $request->validated();

        $attendanceRecord->update($validated);

        return response()->json($attendanceRecord);
    }

    // 削除する
    public function destroy(Attendance $attendanceRecord)
    {
        // 本人かどうかをPolicyでチェック（違えば403）
        $this->authorize('apiDelete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->json(null, 204);
    }
}
