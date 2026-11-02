<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditActorType: string
{
    case USER      = 'user';
    case TELEGRAM  = 'telegram';
    case ANONYMOUS = 'anonymous';
    case SYSTEM    = 'system';
}
