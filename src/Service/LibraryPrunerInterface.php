<?php

declare(strict_types=1);

namespace App\Service;

interface LibraryPrunerInterface
{
    /**
     * @return array{
     *     deleted: int,
     *     bytes: int,
     *     failed: int,
     *     files: list<array{name: string, size: int, createdAt: \DateTimeInterface}>
     * }
     */
    public function prune(\DateTimeImmutable $cutoff, bool $dryRun = false): array;
}
