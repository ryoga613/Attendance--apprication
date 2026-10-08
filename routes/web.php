<?php

use App\Http\Controllers\AdminControllers\AdminAttendanceController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\UserControllers\AttendanceReportController;
use App\Http\Controllers\UserControllers\UserAttendanceController;
use App\Http\Controllers\UserControllers\UserAttendanceCorrectionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// 管理者ルート
Route::prefix('admin')->group(function () {
    Route::get('/login', function () {
        return view('admin.admin-login');
    })->name('admin.login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    // 管理者ミドルウェア
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('admin.attendance.list');
        Route::get('/staff/list', [AdminAttendanceController::class, 'staffList'])->name('admin.staff.list');
        Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'detail'])->name('admin.attendance.show');
        Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'staffAttendance'])->name('admin.attendance.staff');
    });

});

// 一般ユーザーミドルウェア
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/attendance', [UserAttendanceController::class, 'attendanceRegisterForm'])->name('attendance.register.form');
    Route::post('/attendance', [UserAttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/list', [UserAttendanceController::class, 'index'])->name('attendance.list');
    Route::get('/attendance/report', [AttendanceReportController::class, 'index'])->name('attendance.report');

    Route::get('/attendance/detail/{id}', [UserAttendanceController::class, 'detail'])->name('attendance.show');

    Route::get('/attendance/{id}', [UserAttendanceController::class, 'detail'])->name('attendance.show');

    Route::post('/attendance/{id}', [UserAttendanceCorrectionController::class, 'store'])->name('attendanceCorrection.store');
    Route::post('/logout', [LogoutController::class, 'logout'])->name('user.logout');
    Route::get('/stamp_correction_request/list', [UserAttendanceCorrectionController::class, 'index'])->name('attendance.correction.index');
    Route::get('/application/{id}', [UserAttendanceController::class, 'detail'])->name('attendance.show');
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [UserAttendanceCorrectionController::class, 'approveStampCorrectionRequest'])->name('admin.stamp_correction_request.approve');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [UserAttendanceCorrectionController::class, 'updateStampCorrectionRequest'])->name('admin.stamp_correction_request.approve');
});

// Route::post('/logout', LogoutController::class)->name('logout');
