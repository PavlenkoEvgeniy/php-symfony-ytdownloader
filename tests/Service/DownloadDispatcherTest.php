<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Exception\DownloadIntakeDisabledException;
use App\Exception\InsufficientDiskSpaceException;
use App\Message\DownloadMessage;
use App\Service\AuditLoggerInterface;
use App\Service\DiskSpacePolicyInterface;
use App\Service\DownloadDispatcher;
use App\Service\DownloadIntakePolicyInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DownloadDispatcherTest extends TestCase
{
    /**
     * Verifies dispatch persists a queued task and sends a message carrying its id.
     */
    public function testDispatchPersistsTaskAndDispatchesMessageWithTaskId(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(DownloadTask::class));
        $em->expects($this->once())->method('flush');

        $dispatched = null;
        $bus        = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DownloadMessage::class))
            ->willReturnCallback(static function (DownloadMessage $message) use (&$dispatched) {
                $dispatched = $message;

                return new Envelope($message);
            });

        $dispatcher = new DownloadDispatcher(
            $em,
            $bus,
            $this->createStub(AuditLoggerInterface::class),
            $this->createStub(DownloadIntakePolicyInterface::class),
            $this->createStub(DiskSpacePolicyInterface::class),
        );
        $user       = new User()->setEmail('test@test.local');
        $task       = $dispatcher->dispatch(
            'https://youtube.com/watch?v=test',
            'best',
            $user,
            DownloadTask::ORIGIN_TELEGRAM,
            42,
            'Test video',
        );

        $this->assertSame('https://youtube.com/watch?v=test', $task->getUrl());
        $this->assertSame('best', $task->getQuality());
        $this->assertSame(DownloadTask::STATUS_QUEUED, $task->getStatus());
        $this->assertSame($user, $task->getUser());
        $this->assertSame(DownloadTask::ORIGIN_TELEGRAM, $task->getOrigin());
        $this->assertSame(42, $task->getTelegramChatId());
        $this->assertSame('Test video', $task->getTitle());
        $this->assertSame('https://youtube.com/watch?v=test', $dispatched->url);
        $this->assertSame('best', $dispatched->quality);
        $this->assertSame('42', $dispatched->telegramUserId);
        $this->assertSame($task->getId(), $dispatched->taskId);
    }

    /**
     * Verifies a rejected intake persists nothing and dispatches no message.
     */
    public function testDispatchRejectsWhenIntakePolicyThrows(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $policy = $this->createStub(DownloadIntakePolicyInterface::class);
        $policy->method('assertAccepts')->willThrowException(DownloadIntakeDisabledException::create());

        $dispatcher = new DownloadDispatcher(
            $em,
            $bus,
            $this->createStub(AuditLoggerInterface::class),
            $policy,
            $this->createStub(DiskSpacePolicyInterface::class),
        );

        $this->expectException(DownloadIntakeDisabledException::class);

        $dispatcher->dispatch('https://youtube.com/watch?v=test', 'best');
    }

    /**
     * Verifies a rejected disk-space guard persists nothing and dispatches no message.
     */
    public function testDispatchRejectsWhenDiskSpacePolicyThrows(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $diskPolicy = $this->createStub(DiskSpacePolicyInterface::class);
        $diskPolicy->method('assertCanAccept')->willThrowException(InsufficientDiskSpaceException::create(0.0, 1));

        $dispatcher = new DownloadDispatcher(
            $em,
            $bus,
            $this->createStub(AuditLoggerInterface::class),
            $this->createStub(DownloadIntakePolicyInterface::class),
            $diskPolicy,
        );

        $this->expectException(InsufficientDiskSpaceException::class);

        $dispatcher->dispatch('https://youtube.com/watch?v=test', 'best');
    }

    /**
     * Verifies a Telegram request is audited under the linked user's email.
     */
    public function testDispatchAuditsTelegramActorWithUserEmail(): void
    {
        $user = new User()->setEmail('telegram@test.local');

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with(
                AuditAction::DOWNLOAD_REQUEST,
                AuditChannel::TELEGRAM,
                'download_task',
                null,
                'https://youtube.com/watch?v=test',
                true,
                ['quality' => 'best', 'telegram_chat_id' => 42],
                null,
                null,
                $user,
            );

        $dispatcher = new DownloadDispatcher(
            $em,
            $bus,
            $auditLogger,
            $this->createStub(DownloadIntakePolicyInterface::class),
            $this->createStub(DiskSpacePolicyInterface::class),
        );

        $dispatcher->dispatch('https://youtube.com/watch?v=test', 'best', $user, DownloadTask::ORIGIN_TELEGRAM, 42);
    }

    /**
     * Verifies a Telegram request without a user keeps the chat id as the actor.
     */
    public function testDispatchAuditsTelegramFallbackWithoutUser(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $em = $this->createStub(EntityManagerInterface::class);

        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with(
                AuditAction::DOWNLOAD_REQUEST,
                AuditChannel::TELEGRAM,
                'download_task',
                null,
                'https://youtube.com/watch?v=test',
                true,
                ['quality' => 'best', 'telegram_chat_id' => 42],
                AuditActorType::TELEGRAM,
                '42',
                null,
            );

        $dispatcher = new DownloadDispatcher(
            $em,
            $bus,
            $auditLogger,
            $this->createStub(DownloadIntakePolicyInterface::class),
            $this->createStub(DiskSpacePolicyInterface::class),
        );

        $dispatcher->dispatch('https://youtube.com/watch?v=test', 'best', null, DownloadTask::ORIGIN_TELEGRAM, 42);
    }
}
