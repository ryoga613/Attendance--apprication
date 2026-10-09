<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use Illuminate\Support\Facades\Auth;

class AdminAttendanceCorrectionController extends Controller 
{
    // public function approveStampCorrectionRequest($id)
    // {
    //     $user = Auth::user();
    //     $application = AttendanceCorrection::with('proposalBreaks')->findOrFail($id);

    //     return view('admin.admin-application-detail', compact('application', 'user'));
    // }
}
