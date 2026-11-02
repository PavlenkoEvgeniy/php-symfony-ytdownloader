<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadMetric;
use App\Repository\DownloadMetricRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DownloadMetricManager implements DownloadMetricManagerInterface
{
    public function __construct(
        private DownloadMetricRepositoryInterface $downloadMetricRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    /**
     * The lifetime sum of bytes downloaded into the library. It is a persisted
     * accumulator, deliberately not derived from the Source rows: deleting
     * sources must never reduce it (see ADR-0006).
     */
    public function getTotal(): float
    {
        $metric = $this->downloadMetricRepository->getSingleton();

        return $metric?->getTotalBytes() ?? 0.0;
    }

    #[\Override]
    /**
     * Adds bytes into the lifetime counter. Flushing stays the caller's job,
     * so the metric commits together with the Source row it belongs to.
     */
    public function increment(float $size): void
    {
        $metric = $this->downloadMetricRepository->getSingleton();

        if (!$metric instanceof DownloadMetric) {
            $metric = new DownloadMetric();
            $this->entityManager->persist($metric);
        }

        $metric->setTotalBytes(($metric->getTotalBytes() ?? 0.0) + $size);
    }
}
