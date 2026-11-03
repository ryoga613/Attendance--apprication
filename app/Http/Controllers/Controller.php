<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    // 値が指定した日付の形式(例: Y-m-d, Y-m)として正しいか
    protected function isValidDateFormat(mixed $value, string $format): bool
    {
        return is_string($value)
            && Validator::make(['value' => $value], ['value' => "date_format:{$format}"])->passes();
    }
}
