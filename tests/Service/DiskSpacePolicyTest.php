<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\InsufficientDiskSpaceException;
use App\Service\DiskSpacePolicy;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DiskSpacePolicyTest extends TestCase
{
    /**
     * Verifies a threshold above the real free space is reported as low.
     */
    public function testIsLowWhenFreeSpaceIsBelowThreshold(): void
    {
        $policy = new DiskSpacePolicy(\sys_get_temp_dir(), 1_000_000, $this->createStub(LoggerInterface::class));

        $this->assertTrue($policy->isLow());
        $this->assertGreaterThan(0, $policy->getFreeBytes());
    }

    /**
     * Verifies a zero threshold is never low.
     */
    public function testIsNotLowWhenThresholdIsZero(): void
    {
        $policy = new DiskSpacePolicy(\sys_get_temp_dir(), 0, $this->createStub(LoggerInterface::class));

        $this->assertFalse($policy->isLow());
        $this->assertSame(0, $policy->getMinFreeBytes());
    }

    /**
     * Verifies the guard throws while the disk is below the threshold.
     */
    public function testAssertCanAcceptThrowsUnderDiskPressure(): void
    {
        $policy = new DiskSpacePolicy(\sys_get_temp_dir(), 1_000_000, $this->createStub(LoggerInterface::class));

        $this->expectException(InsufficientDiskSpaceException::class);

        $policy->assertCanAccept();
    }

    /**
     * Verifies the guard passes when there is enough free space.
     */
    public function testAssertCanAcceptPassesWithEnoughSpace(): void
    {
        $policy = new DiskSpacePolicy(\sys_get_temp_dir(), 0, $this->createStub(LoggerInterface::class));

        $policy->assertCanAccept();

        $this->addToAssertionCount(1);
    }
}
