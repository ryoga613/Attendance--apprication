<?php

use App\Http\Controllers\AttendanceController;
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
        Route::get('/attendance/list', function () {
            return ' 管理者用の出席一覧ページ(準備中)';
        })->name('admin.attendance.list');
    });

});

// 一般ユーザーミドルウェア
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'attendanceRegisterForm'])->name('attendance.register.form');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    // Route::post
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.list');

});
