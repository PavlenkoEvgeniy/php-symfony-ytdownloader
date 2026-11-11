<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadSettings;
use App\Repository\DownloadSettingsRepositoryInterface;
use App\Service\DownloadProgressNotifier;
use App\Service\ProgressReporterInterface;
use App\Service\TelegramClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DownloadProgressNotifierTest extends TestCase
{
    /**
     * Verifies a zero interval disables progress.
     */
    public function testZeroIntervalDisablesProgress(): void
    {
        $notifier = $this->notifier(new DownloadSettings(true, 0));

        $this->assertNull($notifier->start(42, 'Test'));
    }

    /**
     * Verifies a missing chat disables progress.
     */
    public function testMissingChatDisablesProgress(): void
    {
        $notifier = $this->notifier(new DownloadSettings(true, 5));

        $this->assertNull($notifier->start(0, 'Test'));
    }

    /**
     * Verifies a positive interval returns a reporter.
     */
    public function testPositiveIntervalReturnsReporter(): void
    {
        $notifier = $this->notifier(new DownloadSettings(true, 5));

        $this->assertInstanceOf(ProgressReporterInterface::class, $notifier->start(42, 'Test'));
    }

    /**
     * Verifies unreadable settings are logged and disable progress.
     */
    public function testUnreadableSettingsDisableProgress(): void
    {
        $repository = $this->createStub(DownloadSettingsRepositoryInterface::class);
        $repository->method('getSettings')
            ->willThrowException(new \RuntimeException('database is down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Failed to read the progress interval', ['error' => 'database is down']);

        $notifier = new DownloadProgressNotifier(
            $this->createStub(TelegramClientInterface::class),
            $repository,
            $this->createStub(TranslatorInterface::class),
            new MockClock(),
            $logger,
        );

        $this->assertNull($notifier->start(42, 'Test'));
    }

    private function notifier(DownloadSettings $settings): DownloadProgressNotifier
    {
        $repository = $this->createStub(DownloadSettingsRepositoryInterface::class);
        $repository->method('getSettings')->willReturn($settings);

        return new DownloadProgressNotifier(
            $this->createStub(TelegramClientInterface::class),
            $repository,
            $this->createStub(TranslatorInterface::class),
            new MockClock(),
            $this->createStub(LoggerInterface::class),
        );
    }
}
