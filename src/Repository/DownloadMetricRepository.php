<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadMetric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DownloadMetric>
 */
final class DownloadMetricRepository extends ServiceEntityRepository implements DownloadMetricRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, DownloadMetric::class);
    }

    #[\Override]
    /**
     * The counter is intentionally a singleton: one row, seeded by a migration,
     * incremented on every stored Source.
     */
    public function getSingleton(): ?DownloadMetric
    {
        $metric = $this->createQueryBuilder('m')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $metric instanceof DownloadMetric ? $metric : null;
    }
}
