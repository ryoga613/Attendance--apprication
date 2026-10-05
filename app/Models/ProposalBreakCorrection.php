<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalBreakCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correction_id',
        'proposal_break_id',
        'break_start_at',
        'break_end_at',
    ];

    public function attendanceCorrection(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class);
    }

    public function proposalBreak(): BelongsTo
    {
        return $this->belongsTo(ProposalBreak::class);
    }

    public function getBreakInAttribute()
    {
        return $this->break_start_at;
    }

    public function getBreakOutAttribute()
    {
        return $this->break_end_at;
    }
}
