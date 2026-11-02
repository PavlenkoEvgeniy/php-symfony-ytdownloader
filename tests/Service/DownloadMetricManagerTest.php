<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadMetric;
use App\Repository\DownloadMetricRepositoryInterface;
use App\Service\DownloadMetricManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DownloadMetricManagerTest extends TestCase
{
    /**
     * Verifies a missing singleton is reported as zero.
     */
    public function testGetTotalReturnsZeroWhenMetricIsMissing(): void
    {
        $repository = $this->createStub(DownloadMetricRepositoryInterface::class);
        $repository->method('getSingleton')->willReturn(null);

        $manager = new DownloadMetricManager($repository, $this->createStub(EntityManagerInterface::class));

        $this->assertSame(0.0, $manager->getTotal());
    }

    /**
     * Verifies the stored counter value is returned.
     */
    public function testGetTotalReturnsStoredValue(): void
    {
        $metric = new DownloadMetric();
        $metric->setTotalBytes(42.5);

        $repository = $this->createStub(DownloadMetricRepositoryInterface::class);
        $repository->method('getSingleton')->willReturn($metric);

        $manager = new DownloadMetricManager($repository, $this->createStub(EntityManagerInterface::class));

        $this->assertSame(42.5, $manager->getTotal());
    }

    /**
     * Verifies increment creates and persists the singleton when it is missing.
     */
    public function testIncrementCreatesMetricWhenMissing(): void
    {
        $repository = $this->createStub(DownloadMetricRepositoryInterface::class);
        $repository->method('getSingleton')->willReturn(null);

        $persisted     = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(static function (DownloadMetric $metric) use (&$persisted): void {
                $persisted = $metric;
            });

        $manager = new DownloadMetricManager($repository, $entityManager);
        $manager->increment(5.0);

        $this->assertInstanceOf(DownloadMetric::class, $persisted);
        $this->assertSame(5.0, $persisted->getTotalBytes());
    }

    /**
     * Verifies increment adds to the existing counter without creating a new row.
     */
    public function testIncrementAddsToExistingMetric(): void
    {
        $metric = new DownloadMetric();
        $metric->setTotalBytes(10.0);

        $repository = $this->createStub(DownloadMetricRepositoryInterface::class);
        $repository->method('getSingleton')->willReturn($metric);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $manager = new DownloadMetricManager($repository, $entityManager);
        $manager->increment(2.5);

        $this->assertSame(12.5, $metric->getTotalBytes());
    }
}
