<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// 画面のログインと同じく、メールアドレスとIPごとに1分5回まで
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    // ログイン不要
    Route::get('attendance-records', [AttendanceController::class, 'index']);
    Route::get('attendance-records/{attendanceRecord}', [AttendanceController::class, 'show']);

    // ログイン必須
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('attendance-records', [AttendanceController::class, 'store']);
        Route::put('attendance-records/{attendanceRecord}', [AttendanceController::class, 'update']);
        Route::delete('attendance-records/{attendanceRecord}', [AttendanceController::class, 'destroy']);
    });
});
