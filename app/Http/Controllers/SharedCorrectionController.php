<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AdminControllers\AdminAttendanceCorrectionController;
use App\Http\Controllers\UserControllers\UserAttendanceCorrectionController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SharedCorrectionController extends Controller
{
    public function index()
{
    if (Auth::guard('admin')->check()) {
        return app(AdminAttendanceCorrectionController::class)->index();
    }

    return app(UserAttendanceCorrectionController::class)->index();
}

 public function approveStampCorrectionRequest($id)
{
    if (Auth::guard('admin')->check()) {
        return app(AdminAttendanceCorrectionController::class)->approveStampCorrectionRequest($id);
    }

    return app(UserAttendanceCorrectionController::class)->approveStampCorrectionRequest($id);
}
}
