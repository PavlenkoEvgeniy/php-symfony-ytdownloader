<?php

declare(strict_types=1);

namespace App\Enum;

enum DownloadQuality: string
{
    case BEST     = 'best';
    case MODERATE = 'moderate';
    case POOR     = 'poor';
    case AUDIO    = 'audio';

    public const array VALUES = ['best', 'moderate', 'poor', 'audio'];
}
