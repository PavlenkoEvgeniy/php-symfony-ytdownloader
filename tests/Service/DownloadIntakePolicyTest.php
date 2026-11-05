<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadSettings;
use App\Entity\DownloadTask;
use App\Exception\DownloadIntakeDisabledException;
use App\Repository\DownloadSettingsRepositoryInterface;
use App\Service\DownloadIntakePolicy;
use PHPUnit\Framework\TestCase;

final class DownloadIntakePolicyTest extends TestCase
{
    /**
     * @return array<int, array{string}>
     */
    public function webOriginsProvider(): array
    {
        return [
            [DownloadTask::ORIGIN_UI],
            [DownloadTask::ORIGIN_API],
        ];
    }

    /**
     * Verifies the Telegram surface keeps working when the web intake is off.
     */
    public function testTelegramIsAcceptedWhenWebIntakeDisabled(): void
    {
        $policy = new DownloadIntakePolicy($this->repository(false));

        $policy->assertAccepts(DownloadTask::ORIGIN_TELEGRAM);

        $this->assertFalse($policy->isWebIntakeEnabled());
    }

    /**
     * Verifies web origins are rejected when the web intake is off.
     *
     * @dataProvider webOriginsProvider
     */
    public function testWebOriginsAreRejectedWhenWebIntakeDisabled(string $origin): void
    {
        $policy = new DownloadIntakePolicy($this->repository(false));

        $this->expectException(DownloadIntakeDisabledException::class);

        $policy->assertAccepts($origin);
    }

    /**
     * Verifies every origin is accepted when the web intake is on.
     */
    public function testAllOriginsAreAcceptedWhenWebIntakeEnabled(): void
    {
        $policy = new DownloadIntakePolicy($this->repository(true));

        $policy->assertAccepts(DownloadTask::ORIGIN_UI);
        $policy->assertAccepts(DownloadTask::ORIGIN_API);
        $policy->assertAccepts(DownloadTask::ORIGIN_TELEGRAM);

        $this->assertTrue($policy->isWebIntakeEnabled());
    }

    private function repository(bool $enabled): DownloadSettingsRepositoryInterface
    {
        $repository = $this->createStub(DownloadSettingsRepositoryInterface::class);
        $repository->method('getSettings')->willReturn(
            new DownloadSettings($enabled),
        );

        return $repository;
    }
}
