<?php

declare(strict_types=1);

namespace App\Service;

interface OrphanFilePrunerInterface
{
    /**
     * @return array{name: string, path: string, size: int, modifiedAt: \DateTimeImmutable}[]
     */
    public function findOrphans(): array;

    /**
     * @return array{deleted: int, bytes: int, failed: int}
     */
    public function prune(): array;
}
