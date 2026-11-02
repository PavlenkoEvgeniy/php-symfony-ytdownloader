<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DownloadMetric;
use App\Exception\PropertyIsNotSetException;
use PHPUnit\Framework\TestCase;

final class DownloadMetricTest extends TestCase
{
    /**
     * Verifies a new metric starts at zero.
     */
    public function testDefaults(): void
    {
        $metric = new DownloadMetric();

        $this->assertNull($metric->getId());
        $this->assertSame(0.0, $metric->getTotalBytes());
    }

    /**
     * Verifies the setter stores the value and returns the same instance.
     */
    public function testSetTotalBytesReturnsInstance(): void
    {
        $metric = new DownloadMetric();

        $this->assertSame($metric, $metric->setTotalBytes(1024.5));
        $this->assertSame(1024.5, $metric->getTotalBytes());
    }

    /**
     * Verifies the "or fail" getters return the assigned value.
     */
    public function testTotalBytesOrFailReturnsAssignedValue(): void
    {
        $metric = new DownloadMetric();

        $metric->setTotalBytes(42.0);

        $this->assertSame(42.0, $metric->getTotalBytesOrFail());
    }

    /**
     * Verifies the identifier "or fail" getter throws before persistence.
     */
    public function testIdOrFailThrowsForUnsavedMetric(): void
    {
        $this->expectException(PropertyIsNotSetException::class);

        new DownloadMetric()->getIdOrFail();
    }
}
