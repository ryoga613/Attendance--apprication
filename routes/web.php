<?php

use App\Http\Controllers\AdminControllers\AdminAttendanceController;
use App\Http\Controllers\AdminControllers\AdminAttendanceCorrectionController;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\SharedCorrectionController;
use App\Http\Controllers\UserControllers\AttendanceReportController;
use App\Http\Controllers\UserControllers\UserAttendanceController;
use App\Http\Controllers\UserControllers\UserAttendanceCorrectionController;
use Illuminate\Support\Facades\Route;

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

Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm']);
Route::post('/admin/login', [AdminLoginController::class, 'login'])->name('admin.login');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/attendance', [UserAttendanceController::class, 'attendanceRegisterForm'])->name('attendance.register.form');
    Route::post('/attendance', [UserAttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/list', [UserAttendanceController::class, 'index'])->name('attendance.list');
    Route::get('/attendance/report', [AttendanceReportController::class, 'index'])->name('attendance.report');
    Route::get('/attendance/detail/{id}', [UserAttendanceCorrectionController::class, 'detail'])->name('attendance.detail');
    Route::post('/attendance/{id}', [UserAttendanceCorrectionController::class, 'store'])->name('attendanceCorrection.store');
    Route::get('/application/{id}', [UserAttendanceController::class, 'detail'])->name('application.show');

    Route::post('/logout', [LogoutController::class, 'UserLogout'])->name('user.logout');

});

// 管理者ミドルウェア
Route::middleware(['auth:admin'])->group(function () {

    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminAttendanceCorrectionController::class, 'approveStampCorrectionRequest'])->name('stamp_correction_request.approve');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminAttendanceCorrectionController::class, 'updateStampCorrectionRequest'])->name('admin.stamp_correction_request.approve');
    Route::prefix('admin')->group(function () {
        Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('admin.attendance.list');
        Route::get('/attendance/{id}', [AdminAttendanceController::class, 'detail'])->name('admin.attendance.show');
        Route::post('/attendance/{id}', [AdminAttendanceCorrectionController::class, 'store'])->name('admin.attendanceCorrection.store');
        Route::get('/staff/list', [AdminAttendanceController::class, 'staffList'])->name('admin.staff.list');
        Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'staffAttendance'])->name('admin.attendance.staff');
        Route::post('/logout', [LogoutController::class, 'adminLogout'])->name('logout');
    });

});
// Route::get('/stamp_correction_request/list', [UserAttendanceCorrectionController::class, 'index'])->name('attendance.correction.index');
Route::get('/stamp_correction_request/list', [SharedCorrectionController::class, 'index'])
    ->middleware('auth:web,admin')
    ->name('attendance.correction.index');
