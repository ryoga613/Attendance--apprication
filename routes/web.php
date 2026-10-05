<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\LogoutController;
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

// Route::get('/', function () {
//     return view('user.user-login');
// });
// 管理者ルート
Route::prefix('admin')->group(function () {
    Route::get('/login', function () {
        return view('admin.admin-login');
    })->name('admin.login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    // 管理者ミドルウェア
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/attendance/list', [AdminController::class, 'index'])->name('admin.attendance.list');
        Route::post('/logout', LogoutController::class)->name('admin.logout');
        Route::get('/staff/list', [AdminController::class, 'staffList'])->name('admin.staff.list');

        Route::get('/attendance/staff/{id}', [AdminController::class, 'staffAttendance'])->name('admin.attendance.staff');
    });

});

// 一般ユーザーミドルウェア
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'attendanceRegisterForm'])->name('attendance.register.form');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.list');
    Route::get('/attendance/{id}', [AttendanceController::class, 'detail'])->name('attendance.show');

    Route::post('/attendance/{id}', [AttendanceCorrectionController::class, 'store'])->name('attendanceCorrection.store');
    Route::post('/logout', LogoutController::class)->name('user.logout');
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionController::class, 'stampCorrectionRequestList'])->name('admin.stamp_correction_request.list');
    Route::get('/admin/attendance/{id}', [AdminController::class, 'detail'])->name('admin.attendance.show');
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminController::class, 'approveStampCorrectionRequest'])->name('admin.stamp_correction_request.approve');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminController::class, 'updateStampCorrectionRequest'])->name('admin.stamp_correction_request.approve');

});
