<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\DownloadTask;
use App\Entity\User;
use App\EventSubscriber\DownloadTaskFailureListener;
use App\Message\DownloadMessage;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\DownloadTaskManager;
use App\Service\DownloadTaskManagerInterface;
use App\Service\TelegramNotifierInterface;
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

        $listener = $this->listener(new DownloadTaskManager($entityManager), $task);

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
     * Verifies a Telegram failure notifies the requester with the reason.
     */
    public function testNotifiesTelegramUserOnFinalFailure(): void
    {
        $user = new User()->setEmail('telegram@test.local');
        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
            ->setUser($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($task);
        $entityManager->expects($this->once())->method('flush');

        $notifier = $this->createMock(TelegramNotifierInterface::class);
        $notifier->expects($this->once())
            ->method('notifyDownloadFailure')
            ->with('12345', 'download failed', 5);

        $listener = new DownloadTaskFailureListener(
            new DownloadTaskManager($entityManager),
            $this->repository($task),
            $notifier,
            $this->createStub(LoggerInterface::class),
        );

        $event = new WorkerMessageFailedEvent(
            new Envelope(new DownloadMessage('https://test.local', 'best', '12345', 5)),
            'async',
            new \RuntimeException('download failed')
        );

        ($listener)($event);
    }

    /**
     * Verifies a message scheduled for retry leaves the task untouched.
     */
    public function testDoesNothingWhileRetriesArePlanned(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('find');
        $entityManager->expects($this->never())->method('flush');

        $listener = new DownloadTaskFailureListener(
            new DownloadTaskManager($entityManager),
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $this->createStub(TelegramNotifierInterface::class),
            $this->createStub(LoggerInterface::class),
        );

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
            ->with(5, 'download failed')
            ->willThrowException(new \RuntimeException('database is down'))
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to mark download task as errored', ['error' => 'database is down'])
        ;

        $listener = new DownloadTaskFailureListener(
            $manager,
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $this->createStub(TelegramNotifierInterface::class),
            $logger,
        );

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

        $listener = new DownloadTaskFailureListener(
            new DownloadTaskManager($entityManager),
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $this->createStub(TelegramNotifierInterface::class),
            $this->createStub(LoggerInterface::class),
        );

        $event = new WorkerMessageFailedEvent(
            new Envelope(new DownloadMessage('https://test.local', 'best')),
            'async',
            new \RuntimeException('download failed')
        );

        ($listener)($event);
    }

    private function listener(DownloadTaskManagerInterface $manager, ?DownloadTask $task = null): DownloadTaskFailureListener
    {
        return new DownloadTaskFailureListener(
            $manager,
            $this->repository($task),
            $this->createStub(TelegramNotifierInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function repository(?DownloadTask $task): DownloadTaskRepositoryInterface
    {
        $repository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $repository->method('findById')->willReturn($task);

        return $repository;
    }
}
