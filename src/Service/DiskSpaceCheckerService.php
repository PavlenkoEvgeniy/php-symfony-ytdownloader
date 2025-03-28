<?php

namespace App\Service;

class DiskSpaceCheckerService
{
    public function getFreeSpace(): array
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $used = $total - $free;
        
        return [
            'free' => $this->formatBytes($free),
            'used' => $this->formatBytes($used),
            'total' => $this->formatBytes($total),
            'percentage' => round(($used / $total) * 100, 2)
        ];
    }
    
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}