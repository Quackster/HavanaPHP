<?php

namespace App\Support;

class LegacyMinimailText
{
    public static function format(string $text): string
    {
        return str_replace(["\r\n", "\r", "\n"], '<br>', LegacyBbCode::format($text));
    }
}
