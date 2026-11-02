<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\DownloadTask;
use App\EventSubscriber\DownloadTaskFailureListener;
use App\Message\DownloadMessage;
use App\Service\DownloadTaskManager;
use App\Service\DownloadTaskManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class DownloadTaskFailureListenerTest extends TestCase
{
    /**
     * Verifies a failed message without retries marks its task as errored.
     */
    public function testMarksTaskAsErroredWhenNoRetryIsPlanned(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($task);
        $entityManager->expects($this->once())->method('flush');

        $listener = new DownloadTaskFailureListener(new DownloadTaskManager($entityManager), $this->createStub(LoggerInterface::class));

        $event = new WorkerMessageFailedEvent(
            new Envelope(new DownloadMessage('https://test.local', 'best', '', 5)),
            'async',
            new \RuntimeException('download failed')
        );

        ($listener)($event);

        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatus());
        $this->assertSame('download failed', $task->getErrorMessage());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getFinishedAt());
    }

    /**
     * Verifies a message scheduled for retry leaves the task untouched.
     */
    public function testDoesNothingWhileRetriesArePlanned(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('find');
        $entityManager->expects($this->never())->method('flush');

        $listener = new DownloadTaskFailureListener(new DownloadTaskManager($entityManager), $this->createStub(LoggerInterface::class));

        $event = new WorkerMessageFailedEvent(
            new Envelope(new DownloadMessage('https://test.local', 'best', '', 5)),
            'async',
            new \RuntimeException('temporary failure')
        );
        $event->setForRetry();

        ($listener)($event);
    }

    /**
     * Verifies a database failure while marking the task is logged and does not escape.
     */
    public function testLogsWhenMarkingTaskFails(): void
    {
        $manager = $this->createMock(DownloadTaskManagerInterface::class);
        $manager->expects($this->once())
            ->method('markError')
            ->with(5)
            ->willThrowException(new \RuntimeException('database is down'))
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to mark download task as errored', ['error' => 'database is down'])
        ;

        $listener = new DownloadTaskFailureListener($manager, $logger);

        $event = new WorkerMessageFailedEvent(
            new Envelope(new DownloadMessage('https://test.local', 'best', '', 5)),
            'async',
            new \RuntimeException('download failed')
        );

        ($listener)($event);
    }

    /**
     * Verifies a failed message without a task id is ignored.
     */
    public function testIgnoresMessagesWithoutTaskId(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('find');

        $listener = new DownloadTaskFailureListener(new DownloadTaskManager($entityManager), $this->createStub(LoggerInterface::class));

        $event = new WorkerMessageFailedEvent(
            new Envelope(new DownloadMessage('https://test.local', 'best')),
            'async',
            new \RuntimeException('download failed')
        );

        ($listener)($event);
    }
}
