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
        'attendance_id',
        'correction_type',
        'correction_date',
        'correction_start_time',
        'correction_end_time',
        'correction_break_time',
        'correction_work_time',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function proposalBreakCorrections(): HasMany
    {
        return $this->hasMany(ProposalBreakCorrection::class);
    }
}
