<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexAttendanceRequest;
use App\Http\Requests\Api\StoreAttendanceRequest;
use App\Http\Requests\Api\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    use AuthorizesRequests;

    // 一覧を取得する(一般ユーザーは本人分のみ、管理者は全員分)
    public function index(IndexAttendanceRequest $request)
    {
        $user = $request->user();
        $isAdmin = (bool) $user->admin_status;

        // 一般ユーザーが他人のuser_idを指定した場合は見せない
        if (! $isAdmin && $request->filled('user_id') && $request->integer('user_id') !== $user->id) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        $attendanceRecord_records = Attendance::latest()
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('work_date', $request->date))
            ->when($request->filled('month'), function ($query) use ($request) {
                $month = Carbon::createFromFormat('!Y-m', $request->month);

                $query->whereBetween('work_date', [$month->toDateString(), $month->endOfMonth()->toDateString()]);
            })
            ->paginate($request->per_page);

        return AttendanceResource::collection($attendanceRecord_records);
    }

    // 詳細を取得する(本人か管理者のみ)
    public function show(int $id)
    {

        $attendance = Attendance::find($id);

        if (! $attendance) {
            return response()->json([
                'message' => '勤怠情報が見つかりませんでした。',
            ], 404);
        }

        if (Gate::denies('apiView', $attendance)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

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
