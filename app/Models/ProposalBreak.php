<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'start_time',
        'end_time',
        'break_time',
    ];

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance() : BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
    
}
