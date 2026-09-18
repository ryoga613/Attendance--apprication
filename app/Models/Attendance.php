<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProposalBreak;
use App\Models\AttendanceCorrection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'start_time',
        'end_time',
        'break_time',
        'work_time',
    ];

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proposalBreaks() : HasMany
    {
        return $this->hasMany(ProposalBreak::class);
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
