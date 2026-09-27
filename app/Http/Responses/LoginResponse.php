<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = auth()->user();
        if ($request->is('admin/login')) {
            if ($user->admin_status) {
                return redirect()->route('admin.attendance.list');
            }

            return redirect()->route('admin.login')->withErrors(['email' => 'ログイン情報が登録されていません。']);
        }

        return redirect()->route('attendance.register.form');
    }
}
