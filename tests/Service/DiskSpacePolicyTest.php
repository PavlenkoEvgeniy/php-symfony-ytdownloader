<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadSettings;
use App\Exception\InsufficientDiskSpaceException;
use App\Repository\DownloadSettingsRepositoryInterface;
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
        $policy = $this->policy(minFreeDiskMb: 1_000_000);

        $this->assertTrue($policy->isLow());
        $this->assertGreaterThan(0, $policy->getFreeBytes());
    }

    /**
     * Verifies a stored threshold wins over the deployment default.
     */
    public function testStoredThresholdWinsOverDeploymentDefault(): void
    {
        $policy = $this->policy(minFreeDiskMb: 7, defaultMinFreeMb: 5120);

        $this->assertSame(7 * 1024 * 1024, $policy->getMinFreeBytes());
    }

    /**
     * Verifies an unset threshold falls back to the deployment default.
     */
    public function testUnsetThresholdFallsBackToDeploymentDefault(): void
    {
        $policy = $this->policy(minFreeDiskMb: null, defaultMinFreeMb: 5120);

        $this->assertSame(5120 * 1024 * 1024, $policy->getMinFreeBytes());
    }

    /**
     * Verifies a stored zero disables the guard despite a non-zero default.
     */
    public function testZeroThresholdDisablesGuardDespiteDefault(): void
    {
        $policy = $this->policy(minFreeDiskMb: 0, defaultMinFreeMb: 5120);

        $this->assertSame(0, $policy->getMinFreeBytes());
        $this->assertFalse($policy->isLow());

        $policy->assertCanAccept();

        $this->addToAssertionCount(1);
    }

    /**
     * Verifies a threshold saved after construction applies to the next check.
     */
    public function testThresholdChangeTakesEffectOnTheNextCall(): void
    {
        $settings = new DownloadSettings();
        $policy   = $this->policyWith($settings, defaultMinFreeMb: 5120);

        $this->assertSame(5120 * 1024 * 1024, $policy->getMinFreeBytes());

        $settings->setMinFreeDiskMb(7);

        $this->assertSame(7 * 1024 * 1024, $policy->getMinFreeBytes());
    }

    /**
     * Verifies the guard throws while the disk is below the threshold.
     */
    public function testAssertCanAcceptThrowsUnderDiskPressure(): void
    {
        $policy = $this->policy(minFreeDiskMb: 1_000_000);

        $this->expectException(InsufficientDiskSpaceException::class);

        $policy->assertCanAccept();
    }

    /**
     * Verifies the guard passes when there is enough free space.
     */
    public function testAssertCanAcceptPassesWithEnoughSpace(): void
    {
        $policy = $this->policy(minFreeDiskMb: 0);

        $policy->assertCanAccept();

        $this->addToAssertionCount(1);
    }

    private function policy(?int $minFreeDiskMb, int $defaultMinFreeMb = 5120): DiskSpacePolicy
    {
        return $this->policyWith(new DownloadSettings(minFreeDiskMb: $minFreeDiskMb), $defaultMinFreeMb);
    }

    private function policyWith(DownloadSettings $settings, int $defaultMinFreeMb): DiskSpacePolicy
    {
        $repository = $this->createStub(DownloadSettingsRepositoryInterface::class);
        $repository->method('getSettings')->willReturn($settings);

        return new DiskSpacePolicy(
            \sys_get_temp_dir(),
            $defaultMinFreeMb,
            $repository,
            $this->createStub(LoggerInterface::class),
        );
    }
}
