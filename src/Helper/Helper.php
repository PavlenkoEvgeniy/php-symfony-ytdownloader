<?php

declare(strict_types=1);

namespace App\Helper;

final class Helper
{
    public static function formatBytes(int|float|null $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = (float) \max((float) ($bytes ?? 0), 0);
        $pow   = $bytes > 0 ? (int) \floor(\log($bytes, 1024)) : 0;
        $pow   = \min(\max($pow, 0), \count($units) - 1);

        $bytes /= \pow(1024.0, (float) $pow);

        return \sprintf('%.1f %s', \round($bytes, $precision), $units[$pow]);
    }

    /**
     * @return array<string, string|float>
     */
    public static function getFreeSpace(): array
    {
        $free  = \disk_free_space('/') ?: 0.0;
        $total = \disk_total_space('/') ?: 0.0;
        $used  = $total - $free;

        return [
            'free'       => Helper::formatBytes($free),
            'used'       => Helper::formatBytes($used),
            'total'      => Helper::formatBytes($total),
            'percentage' => $total > 0 ? \round(($used / $total) * 100.0, 2) : 0.0,
        ];
    }
}
