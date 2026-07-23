<?php

namespace App\Support;

class TemporalContext
{
    public static function season(): string
    {
        $month = (int) now()->format('n');

        return match (true) {
            in_array($month, [12, 1, 2], true) => 'inverno',
            in_array($month, [3, 4, 5], true) => 'primavera',
            in_array($month, [6, 7, 8], true) => 'estate',
            default => 'autunno',
        };
    }

    public static function timeOfDay(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 6 => 'notte',
            $hour < 12 => 'mattina',
            $hour < 17 => 'pomeriggio',
            $hour < 21 => 'sera',
            default => 'notte',
        };
    }
}
