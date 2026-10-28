<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'comment',
    ];

    use HasFactory;

    // ---- 定数(所定の勤務条件はここだけで管理) ----
    public const STANDARD_START_HOUR = 9;

    public const STANDARD_END_HOUR = 18;

    public const STANDARD_DAILY_MINUTES = 8 * 60; // 所定労働時間 8時間

    public const LONG_WORK_TIME_MINUTES = 10 * 60; // 長時間労働 10時間

    // $fillable など、既存の設定はそのまま残してください
    protected $casts = [
        'work_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    // ---- リレーション ----
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

    // 計算

    /** 休憩合計(分) */
    public function getBreakMinutesAttribute(): int
    {
        return (int) $this->breaks->sum(
            fn ($b) => ($b->break_start_at && $b->break_end_at)
                ? $b->break_start_at->diffInMinutes($b->break_end_at)
                : 0
        );
    }

    /** 実労働（分）= 勤務時間 - 休憩時間。未退勤は0 */
    public function getTotalWorkMinutesAttribute(): int
    {
        if (! $this->clock_in_at || ! $this->clock_out_at) {
            return 0;
        }

        $totalMinutes = $this->clock_in_at->diffInMinutes($this->clock_out_at);

        return max($totalMinutes - $this->break_minutes, 0);
    }

    /** 残業(分) */
    public function getTotalOvertimeMinutesAttribute(): int
    {
        return max(
            $this->total_work_minutes - self::STANDARD_DAILY_MINUTES,
            0
        );
    }

    /** 遅刻なら 1 */
    public function getLateCountAttribute(): int
    {
        return $this->clock_in_at
            && $this->clock_in_at->gt($this->work_date->copy()->setTime(self::STANDARD_START_HOUR, 0)
            )
            ? 1 : 0;
    }

    /** 早退なら 1 */
    public function getEarlyLeaveCountAttribute(): int
    {
        return $this->clock_out_at
            && $this->clock_out_at->lt(
                $this->work_date->copy()->setTime(self::STANDARD_END_HOUR, 0)
            )
            ? 1 : 0;
    }

    /** 所定労働時間を超えたら 1 */
    public function getLongWorkCountAttribute(): int
    {
        return $this->total_work_minutes > self::LONG_WORK_TIME_MINUTES ? 1 : 0;
    }

    // ---- 表示用(既存のbladeを壊さないよう、名前はそのまま) ----

    public function getDateAttribute()
    {
        return $this->work_date?->copy()->settings(['toStringFormat' => 'm月 d日']);
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

    public function getTotalBreakTimeAttribute()
    {
        return $this->break_minutes > 0
            ? sprintf(
                '%02d:%02d',
                intdiv($this->break_minutes, 60),
                $this->break_minutes % 60
            )
            : null;
    }

    public function getTotalTimeAttribute()
    {
        if (! $this->clock_in_at || ! $this->clock_out_at) {
            return null;
        }

        return sprintf(
            '%02d:%02d',
            intdiv($this->total_work_minutes, 60),
            $this->total_work_minutes % 60
        );
    }

    public function getNewDateAttribute()
    {
        return $this->clock_in_at?->copy()->settings(['toStringFormat' => 'm月 d日']);
    }

    public function getNewClockInAttribute()
    {
        return $this->clock_in_at?->copy()->settings(['toStringFormat' => 'H:i']);
    }

    public function getNewClockOutAttribute()
    {
        return $this->clock_out_at?->copy()->settings(['toStringFormat' => 'H:i']);
    }

    public function getApplicationDateAttribute()
    {
        return $this->attendanceCorrections->first()?->created_at->format('Y-m-d');
    }
}
