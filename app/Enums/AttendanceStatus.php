<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case OFF_DUTY = '勤務外';
    case ON_DUTY = '出勤中';
    case ON_BREAK = '休憩中';
    case CLOCKED_OUT = '退勤済';
}
