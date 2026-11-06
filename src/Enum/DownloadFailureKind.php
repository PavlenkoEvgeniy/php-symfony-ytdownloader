<?php

declare(strict_types=1);

namespace App\Enum;

enum DownloadFailureKind
{
    case NONE;
    case TRANSIENT;
    case PERMANENT;
}
