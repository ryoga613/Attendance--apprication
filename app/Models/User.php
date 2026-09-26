<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
        'attendance_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'attendance_status' => AttendanceStatus::class,
    ];

    public function getAttendanceStatusAttribute($value): string
    {
        // DBから取り出した値（あるいはEnum）を判定して日本語文字列を返す
        return match ($value) {
            'off_duty', AttendanceStatus::OFF_DUTY->value, AttendanceStatus::OFF_DUTY => '勤務外',
            'on_duty', AttendanceStatus::ON_DUTY->value, AttendanceStatus::ON_DUTY => '出勤中',
            'on_break', AttendanceStatus::ON_BREAK->value, AttendanceStatus::ON_BREAK => '休憩中',
            'clocked_out', AttendanceStatus::CLOCKED_OUT->value, AttendanceStatus::CLOCKED_OUT => '退勤後',
            default => $value ?? '勤務外', // 未設定時の初期値
        };
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function attendance_status(): AttendanceStatus
    {
        return $this->attendance_status;
    }
}
