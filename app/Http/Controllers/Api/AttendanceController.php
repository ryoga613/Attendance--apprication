<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\IndexAttendanceRequest;
use App\Http\Requests\Api\StoreAttendanceRequest;
use App\Http\Requests\Api\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    use AuthorizesRequests;

    // 一覧を取得する
    public function index(IndexAttendanceRequest $request)
    {
        Gate::authorize('viewAny', Attendance::class);

        $perPage = $request->per_page;

        $user = Auth::user();

        $date = $request->date;

        $attendanceRecord_records = Attendance::latest()
            ->where('user_id', $user->id)
            ->where('work_date', $date)
            ->paginate($perPage);

        return AttendanceResource::collection($attendanceRecord_records);
    }

    // 詳細を取得する
    public function show(int $id)
    {

        $attendance = Attendance::find($id);

        if (! $attendance) {
            return response()->json([
                'message' => '勤怠情報が見つかりませんでした。',
            ], 404);
        }

        Gate::authorize('view', $attendance);

        $attendance->load(['user', 'breaks', 'attendanceCorrections']);

        return new AttendanceResource($attendance);
    }

    // 新規登録する
    public function store(StoreAttendanceRequest $request)
    {
        Gate::authorize('store', Attendance::class);

        $validated = $request->validated();

        $attendanceRecord = $request->user()
            ->attendances()
            ->create($validated);

        $attendanceRecord->load(['user', 'breaks']);

        return (new AttendanceResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    // 更新する
    public function update(UpdateAttendanceRequest $request, Attendance $attendanceRecord)
    {

        if (Gate::denies('apiUpdate', $attendanceRecord)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        $validated = $request->validated();

        $attendanceRecord->update($validated);

        $attendanceRecord->load(['user', 'breaks']);

        return new AttendanceResource($attendanceRecord);
    }

    // 削除する
    public function destroy(Attendance $attendanceRecord)
    {

        if (Gate::denies('apiDelete', $attendanceRecord)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        $attendanceRecord->delete();

        return response()->json(null, 204);
    }
}
