<?php

namespace App\Support;

class TableColorPalette
{
    /** @var list<string> */
    public const COLORS = [
        '#e11d48', // rose
        '#2563eb', // blue
        '#16a34a', // green
        '#d97706', // amber
        '#7c3aed', // violet
        '#0891b2', // cyan
        '#db2777', // pink
        '#65a30d', // lime
        '#ea580c', // orange
        '#4f46e5', // indigo
    ];

    public static function forKey(string $stableKey): string
    {
        if (preg_match('/(\d+)/', $stableKey, $m)) {
            $index = ((int) $m[1] - 1) % count(self::COLORS);

            return self::COLORS[max(0, $index)];
        }

        return self::COLORS[abs(crc32($stableKey)) % count(self::COLORS)];
    }

    public static function normalize(?string $hex, string $fallbackKey): string
    {
        if (is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return strtolower($hex);
        }

        return self::forKey($fallbackKey);
    }
}
