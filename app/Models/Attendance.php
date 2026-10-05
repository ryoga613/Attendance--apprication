<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'break_time',
        'work_time',
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

    public function breaks(): HasMany
    {
        return $this->hasMany(ProposalBreak::class);
    }

    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function getDateAttribute()
    {
        return $this->work_date->copy()->settings(['toStringFormat' => 'm月 d日']);
    }

    public function getYearAttribute()
    {
        return $this->work_date?->format('Y 年');
    }

    public function getClockInAttribute()
    {
        return $this->clock_in_at?->format('H:i');
    }

    public function getClockOutAttribute()
    {
        return $this->clock_out_at?->format('H:i');
    }

    private function breakSeconds(): int
    {
        return $this->breaks->sum(
            fn ($b) => $b->break_end_at?->diffInSeconds($b->break_start_at) ?? 0
        );
    }

    public function getTotalBreakTimeAttribute()
    {
        $seconds = $this->breakSeconds();

        return $seconds ? gmdate('H:i:s', $seconds) : null;
    }

    public function getTotalTimeAttribute()
    {
        if (! $this->clock_in_at || ! $this->clock_out_at) {
            return null;
        }

        $seconds = $this->clock_out_at->diffInSeconds($this->clock_in_at) - $this->breakSeconds();

        return gmdate('H:i:s', max($seconds, 0));
    }

    public function getNewDateAttribute()
    {
        return $this->clock_in_at->copy()->settings(['toStringFormat' => 'm月 d日']);
    }

    public function getNewClockInAttribute()
    {
        return $this->clock_in_at->copy()->settings(['toStringFormat' => 'h:i']);

    }

    public function getNewClockOutAttribute()
    {
        return $this->clock_out_at->copy()->settings(['toStringFormat' => 'h:i']);
    }

    public function getApplicationDateAttribute()
    {
        return $this->attendanceCorrections->first()?->created_at->format('Y-m-d');
    }
}
