<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAttendanceRequest;
use App\Http\Requests\Api\UpdateAttendanceRequest;
use App\Models\Attendance;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    use AuthorizesRequests;

    // 一覧を取得する
    public function index()
    {
        Gate::authorize('viewAny', Attendance::class);

        $attendances = Attendance::orderByDesc('work_date')->get();

        return response()->json($attendances);
    }

    // 詳細を取得する
    public function show(int $id)
    {

        $attendance = Attendance::find($id);
        Gate::authorize('view', $attendance);

        if (! $attendance) {
            return response()->json([
                'message' => '勤怠情報が見つかりませんでした。',
            ], 404);
        }

        return response()->json($attendance);
    }

    // 新規登録する
    public function store(StoreAttendanceRequest $request)
    {

        Gate::authorize('store', Attendance::class);

        $validated = $request->validated();

        // user_id はログイン中のユーザーから自動で入れる
        $user = $request->user();
        $validated['user_id'] = $user->id;

        $attendance = Attendance::create($validated);

        return response()->json($attendance, 201);
    }

    // 更新する
    public function update(UpdateAttendanceRequest $request, Attendance $attendanceRecord)
    {
        // 本人かどうかをPolicyでチェック（違えば403）
        // $this->authorize('apiUpdate', $attendanceRecord);
        if (Gate::denies('apiUpdate', $attendanceRecord)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        $validated = $request->validated();

        $attendanceRecord->update($validated);

        return response()->json($attendanceRecord);
    }

    // 削除する
    public function destroy(Attendance $attendanceRecord)
    {
        // 本人かどうかをPolicyでチェック（違えば403）
        // $this->authorize('apiDelete', $attendanceRecord);
        if (Gate::denies('apiUpdate', $attendanceRecord)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        $attendanceRecord->delete();

        return response()->json(null, 204);
    }
}
