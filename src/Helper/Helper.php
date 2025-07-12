<?php

declare(strict_types=1);

namespace App\Helper;

class Helper
{
    /**
     * @psalm-suppress InvalidOperand
     */
    public static function formatBytes(int|float|null $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return sprintf('%.1f %s', round($bytes, $precision), $units[$pow]);
    }
}
