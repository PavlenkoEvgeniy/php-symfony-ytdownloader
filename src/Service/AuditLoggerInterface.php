<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;

interface AuditLoggerInterface
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        AuditAction $action,
        ?AuditChannel $channel = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $targetLabel = null,
        bool $success = true,
        array $metadata = [],
        ?AuditActorType $actorType = null,
        ?string $actorLabel = null,
    ): void;
}
