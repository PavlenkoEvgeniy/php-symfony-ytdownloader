<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Source;

interface SourceRepositoryInterface
{
    public function findOneByFilename(string $filename): ?Source;

    /**
     * @return Source[]
     */
    public function findAllSources(): array;

    /**
     * @return Source[]
     */
    public function findAllOrderedByCreatedAt(string $order): array;

    /**
     * @return string[]
     */
    public function findAllFilenames(): array;
}
