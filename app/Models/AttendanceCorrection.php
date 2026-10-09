<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'clock_in_at',
        'clock_out_at',
        'approval_status',
        'comment',
    ];

    protected $casts = [
        'work_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function AttendanceRecord(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(ProposalBreakCorrection::class);
    }

    public function getNewDateAttribute()
    {
        return $this->clock_in_at->copy()->settings(['toStringFormat' => 'm月 d日']);
    }

    public function getNewClockInAttribute()
    {
        return $this->clock_in_at->copy()->settings(['toStringFormat' => 'H:i']);
    }

    public function getNewClockOutAttribute()
    {
        return $this->clock_out_at->copy()->settings(['toStringFormat' => 'H:i']);
    }

    public function getTotalBreakTimeAttribute()
    {
        $totalBreakSeconds = $this->proposalBreaks->sum(function ($break) {
            if ($break->break_start_at && $break->break_end_at) {
                return $break->break_end_at->diffInSeconds($break->break_start_at);
            }

            return 0;
        });

        return gmdate('H:i:s', $totalBreakSeconds);
    }
}
