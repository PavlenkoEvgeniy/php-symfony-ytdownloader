<?php

declare(strict_types=1);

namespace App\Repository;

interface AuditEventRepositoryInterface
{
    public function countOlderThan(\DateTimeImmutable $before): int;

    public function deleteOlderThan(\DateTimeImmutable $before): int;
}
