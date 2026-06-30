<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class LegacyWordfilter
{
    public static function filterSentence(string $sentence): string
    {
        $words = DB::table('wordfilter')
            ->orderByRaw('length(word) desc')
            ->pluck('word')
            ->map(fn ($word): string => (string) $word)
            ->filter(fn (string $word): bool => $word !== '');

        foreach ($words as $word) {
            if (stripos($sentence, $word) !== false) {
                $sentence = str_ireplace($word, 'bobba', strtolower($sentence));
            }
        }

        return $sentence;
    }
}
