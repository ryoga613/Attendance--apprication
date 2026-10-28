<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// 申請一覧は一般ユーザーと管理者で同じURLのため、ログイン中のガードで表示を切り替える
class SharedCorrectionController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', AttendanceCorrection::class);

        $isAdmin = Auth::guard('admin')->check();
        $user = Auth::user();

        // 管理者は全員分、一般ユーザーは自分の申請のみ
        $applications = AttendanceCorrection::with(['user', 'AttendanceRecord'])
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();

        $formattedApplications = $applications->map(function ($application) {
            return [
                'id' => $application->id,
                'date' => $application->AttendanceRecord->work_date,
                'application_date' => $application->created_at,
                'clock_in_at' => $application->clock_in_at,
                'clock_out_at' => $application->clock_out_at,
                'approval_status' => $application->approval_status->value,
                'comment' => $application->comment,
            ];
        });

        $view = $isAdmin ? 'admin.admin-application-list' : 'user.user-application-list';

        return view($view, compact('applications', 'formattedApplications', 'user'));
    }
}
