<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'break_start_at',
        'break_end_at',
        'break_time',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function proposalBreakCorrections(): HasMany
    {
        return $this->hasMany(ProposalBreakCorrection::class);
    }
}
