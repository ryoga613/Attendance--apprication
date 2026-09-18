<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\ProposalBreak;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class User extends Authenticatable
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
    ];


    public function attendances() : HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function proposalBreak(): HasManyThrough
    {
        return $this->hasManyThrough(ProposalBreak::class, AttendanceCorrection::class);
    }



    public function attendanceCorrections() : HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function proposalBreakCorrections() : HasManyThrough
    {
        return $this->hasManyThrough(ProposalBreakCorrection::class, AttendanceCorrection::class);
    }

}
