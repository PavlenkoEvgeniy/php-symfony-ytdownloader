<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditChannel: string
{
    case UI       = 'ui';
    case ADMIN    = 'admin';
    case API_V1   = 'api_v1';
    case API_V2   = 'api_v2';
    case TELEGRAM = 'telegram';
    case SYSTEM   = 'system';
}
