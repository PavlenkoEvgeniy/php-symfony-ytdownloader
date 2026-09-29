<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadMetric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DownloadMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DownloadMetric::class);
    }

    /**
     * The counter is intentionally a singleton: one row, seeded by a migration,
     * incremented on every stored Source.
     */
    public function getSingleton(): ?DownloadMetric
    {
        return $this->findOneBy([]);
    }
}
