<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case PENDING = '承認待ち';
    case APPROVED = '承認済み';
}
