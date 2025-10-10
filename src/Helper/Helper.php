<?php

declare(strict_types=1);

namespace App\Helper;

final class Helper
{
    /**
     * @psalm-suppress InvalidOperand
     */
    public static function formatBytes(int|float|null $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = \max($bytes, 0);
        $pow   = \floor(($bytes ? \log($bytes) : 0) / \log(1024));
        $pow   = \min($pow, \count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return \sprintf('%.1f %s', \round($bytes, $precision), $units[$pow]);
    }

    public static function getFreeSpace(): array
    {
        $free  = \disk_free_space('/');
        $total = \disk_total_space('/');
        $used  = $total - $free;

        return [
            'free'       => Helper::formatBytes($free),
            'used'       => Helper::formatBytes($used),
            'total'      => Helper::formatBytes($total),
            'percentage' => \round(($used / $total) * 100, 2),
        ];
    }
}
