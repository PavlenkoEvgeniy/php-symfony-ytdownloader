<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Message\DownloadMessage;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\DownloadRetryService;
use App\Service\DownloadTaskManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DownloadRetryServiceTest extends TestCase
{
    /**
     * Verifies an error task is queued again, dispatched and audited.
     */
    public function testReDownloadQueuesErrorTask(): void
    {
        $user = new User()->setEmail('admin@test.local');
        $task = new DownloadTask();

        $task->setUrl('https://test.local/video')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
            ->setStatus(DownloadTask::STATUS_ERROR);

        $repository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $repository->method('findById')->willReturn($task);

        $manager = $this->createMock(DownloadTaskManagerInterface::class);
        $manager->expects($this->once())->method('requeueError')->with(7)->willReturn(true);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (object $message): bool => $message instanceof DownloadMessage
                && 'https://test.local/video' === $message->url
                && 'best' === $message->quality
                && '12345' === $message->telegramUserId
                && 7 === $message->taskId))
            ->willReturn(new Envelope(new DownloadMessage('https://test.local/video', 'best', '12345', 7)));

        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with(
                AuditAction::DOWNLOAD_RETRY,
                AuditChannel::ADMIN,
                'download_task',
                7,
                'https://test.local/video',
                true,
                ['quality' => 'best', 'origin' => DownloadTask::ORIGIN_TELEGRAM],
                null,
                null,
                $user,
            );

        $service = new DownloadRetryService($repository, $manager, $bus, $auditLogger);

        $this->assertTrue($service->reDownload(7, $user, AuditChannel::ADMIN));
    }

    /**
     * Verifies only error tasks can be re-downloaded.
     */
    public function testReDownloadRejectsNonErrorTask(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://test.local/video')->setQuality('best')->setStatus(DownloadTask::STATUS_QUEUED);

        $repository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $repository->method('findById')->willReturn($task);

        $manager = $this->createMock(DownloadTaskManagerInterface::class);
        $manager->expects($this->never())->method('requeueError');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->never())->method('log');

        $service = new DownloadRetryService($repository, $manager, $bus, $auditLogger);

        $this->assertFalse($service->reDownload(7));
    }

    /**
     * Verifies a task that cannot be requeued is reported as not retried.
     */
    public function testReDownloadReturnsFalseWhenRequeueFails(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://test.local/video')->setQuality('best')
            ->setStatus(DownloadTask::STATUS_ERROR)
        ;

        $repository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $repository->method('findById')->willReturn($task);

        $manager = $this->createMock(DownloadTaskManagerInterface::class);
        $manager->expects($this->once())->method('requeueError')->with(7)->willReturn(false);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->never())->method('log');

        $service = new DownloadRetryService($repository, $manager, $bus, $auditLogger);

        $this->assertFalse($service->reDownload(7));
    }
}
