<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Message\DownloadMessage;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\QueueResetService;
use App\Service\QueueResetServiceInterface;
use App\Service\TaskProcessingLeaseInterface;
use App\Service\TaskProcessingLockInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class QueueResetServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private DownloadTaskRepositoryInterface $downloadTaskRepository;
    private QueueResetServiceInterface $queueResetService;

    public function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager                = self::getContainer()->get('doctrine')->getManager();
        $this->entityManager          = $entityManager;
        $this->connection             = $entityManager->getConnection();
        $this->downloadTaskRepository = self::getContainer()->get(DownloadTaskRepositoryInterface::class);
        $this->queueResetService      = self::getContainer()->get(QueueResetServiceInterface::class);

        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollback();
            $this->entityManager->clear();
        }

        parent::tearDown();
    }

    private function createTask(string $status): DownloadTask
    {
        $task = new DownloadTask();
        $task->setUrl('https://youtube.com/watch?v=test')->setQuality('best')->setStatus($status);
        $this->entityManager->persist($task);

        return $task;
    }

    private function insertMessage(string $queueName, ?string $deliveredAt): void
    {
        $this->connection->executeStatement(
            'INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at)
             VALUES (:body, :headers, :queueName, NOW(), NOW(), :deliveredAt)',
            [
                'body'        => '{}',
                'headers'     => '[]',
                'queueName'   => $queueName,
                'deliveredAt' => $deliveredAt,
            ],
        );
    }

    /**
     * Verifies reset moves processing tasks to queued and dispatches a fresh message for each.
     */
    public function testResetReturnsOnlyProcessingTasksToQueued(): void
    {
        $before         = $this->downloadTaskRepository->getStatusCounts();
        $beforeMessages = (int) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'download_queue'",
        )->fetchOne();

        $this->createTask(DownloadTask::STATUS_PROCESSING);
        $this->createTask(DownloadTask::STATUS_PROCESSING);
        $this->createTask(DownloadTask::STATUS_QUEUED);
        $this->createTask(DownloadTask::STATUS_ERROR);
        $this->createTask(DownloadTask::STATUS_SUCCESS);
        $this->entityManager->flush();

        $result = $this->queueResetService->reset();

        $counts = $this->downloadTaskRepository->getStatusCounts();
        $this->assertSame($before[DownloadTask::STATUS_PROCESSING] + 2, $result['processing']);
        $this->assertSame(0, $counts[DownloadTask::STATUS_PROCESSING]);
        $this->assertSame($before[DownloadTask::STATUS_QUEUED] + 3, $counts[DownloadTask::STATUS_QUEUED]);
        $this->assertSame($before[DownloadTask::STATUS_ERROR] + 1, $counts[DownloadTask::STATUS_ERROR]);
        $this->assertSame($before[DownloadTask::STATUS_SUCCESS] + 1, $counts[DownloadTask::STATUS_SUCCESS]);

        // Each reset task gets a fresh message, even if the old one was already acked.
        $afterMessages = (int) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'download_queue'",
        )->fetchOne();
        $this->assertSame($beforeMessages + 2, $afterMessages);
    }

    /**
     * Verifies reset skips a processing task whose attempt is still live.
     */
    public function testResetSkipsTaskWithLiveAttempt(): void
    {
        $task = $this->createTask(DownloadTask::STATUS_PROCESSING);
        $this->entityManager->flush();

        $lock  = self::getContainer()->get(TaskProcessingLockInterface::class);
        $lease = $lock->tryAcquire($task->getIdOrFail());

        $this->assertNotNull($lease);

        try {
            $result = $this->queueResetService->reset();
            $this->assertSame(1, $result['skipped']);

            $this->entityManager->clear();
            $reloaded = $this->downloadTaskRepository->findById($task->getIdOrFail());

            $this->assertInstanceOf(DownloadTask::class, $reloaded);
            $this->assertSame(DownloadTask::STATUS_PROCESSING, $reloaded->getStatus());
        } finally {
            $lease->release();
        }
    }

    /**
     * Verifies reset redelivers in-flight download messages and leaves other queues alone.
     */
    public function testResetRedeliversInFlightMessagesOfDownloadQueueOnly(): void
    {
        $beforeStuck = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NOT NULL',
        )->fetchOne();
        $beforeStuckResettable = (int) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NOT NULL AND queue_name = 'download_queue'",
        )->fetchOne();

        $this->insertMessage('download_queue', '2026-01-01 00:00:00');
        $this->insertMessage('download_queue', null);
        $this->insertMessage('failed_queue', '2026-01-01 00:00:00');
        $this->insertMessage('other_queue', '2026-01-01 00:00:00');

        $result = $this->queueResetService->reset();

        $this->assertSame($beforeStuckResettable + 1, $result['messages']);

        $remainingStuck = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NOT NULL',
        )->fetchOne();
        $this->assertSame($beforeStuck - $beforeStuckResettable + 2, $remainingStuck);
    }

    /**
     * Verifies a task that disappeared between lookup and reset is skipped.
     */
    public function testResetSkipsTaskThatCannotBeReset(): void
    {
        $task = $this->createTask(DownloadTask::STATUS_PROCESSING);
        $this->entityManager->flush();

        $repository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $repository->method('findProcessingTasks')->willReturn([$task]);
        $repository->method('resetTask')->willReturn(false);

        $lease = $this->createStub(TaskProcessingLeaseInterface::class);

        $lock = $this->createStub(TaskProcessingLockInterface::class);
        $lock->method('tryAcquire')->willReturn($lease);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $connection = $this->createStub(Connection::class);
        $connection->method('transactional')->willReturn(0);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $service = new QueueResetService($entityManager, $repository, $bus, $lock);

        $this->assertSame(['processing' => 0, 'messages' => 0, 'skipped' => 1], $service->reset());
    }

    /**
     * Verifies a reset Telegram task passes its chat id to the fresh message.
     */
    public function testResetPassesTelegramChatIdToDispatchedMessage(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://test.local/telegram-reset')
            ->setQuality('best')
            ->setStatus(DownloadTask::STATUS_PROCESSING)
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
        ;
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $taskId = $task->getIdOrFail();

        $dispatched = [];
        $bus        = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            static function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            },
        );

        $lock    = self::getContainer()->get(TaskProcessingLockInterface::class);
        $service = new QueueResetService($this->entityManager, $this->downloadTaskRepository, $bus, $lock);

        $service->reset();

        $ours = \array_values(\array_filter(
            $dispatched,
            static fn (object $message): bool => $message instanceof DownloadMessage && $taskId === $message->taskId,
        ));

        $this->assertCount(1, $ours);
        $this->assertSame('12345', $ours[0]->telegramUserId);
    }
}
