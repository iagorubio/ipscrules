<?php

namespace App\Support;

/** Comprueba que una cita es literal respecto a un texto (tolerando mayúsculas, espacios y comillas tipográficas). */
final class QuoteMatcher
{
    public static function contains(string $haystack, string $quote): bool
    {
        $h = self::norm($haystack);
        $parts = preg_split('/\s*(?:\.{3}|…)\s*/u', $quote);
        $parts = array_filter(array_map([self::class, 'norm'], $parts), fn ($p) => mb_strlen($p) >= 8);
        if (! $parts) {
            return false;
        }
        foreach ($parts as $p) {
            if (! str_contains($h, $p)) {
                return false;
            }
        }

        return true;
    }

    public static function norm(string $s): string
    {
        $s = str_replace(['“', '”', '„', '‟'], '"', $s);
        $s = str_replace(['‘', '’', '´'], "'", $s);
        $s = str_replace(['–', '—', '‑'], '-', $s);
        $s = mb_strtolower($s);

        return trim((string) preg_replace('/[\s"\'.,;:]+/u', ' ', $s));
    }
}
