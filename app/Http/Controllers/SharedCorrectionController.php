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
        // 管理者として扱うのは、adminガードでログインしていて、今も管理者権限を持つ人だけ
        // (ログイン後に管理者権限を外された人に、全員分の申請を見せないため)
        $admin = Auth::guard('admin')->user();
        $isAdmin = $admin && $admin->admin_status;

        // 管理者でなければ、一般ユーザーとしてログインしている必要がある
        $user = $isAdmin ? $admin : Auth::guard('web')->user();
        abort_if(! $user, 403, 'このページにアクセスする権限がありません。');

        Gate::forUser($user)->authorize('viewAny', AttendanceCorrection::class);

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
