<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Форматирование для шаблонов: деньги, даты, склонения. */
class Fmt
{
    public static function money(int $price): string
    {
        return $price === 0 ? 'Бесплатно' : number_format($price, 0, '', ' ').' ₸';
    }

    public static function date(string $iso): Carbon
    {
        return Carbon::parse($iso)->locale('ru');
    }

    /** Склонение: plural(5, 'просмотр', 'просмотра', 'просмотров') → «просмотров». */
    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $last = $n % 10;

        return match (true) {
            $n > 10 && $n < 20 => $many,
            $last === 1 => $one,
            $last >= 2 && $last <= 4 => $few,
            default => $many,
        };
    }

    /** 297 → «4:57». */
    public static function clock(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }
}
