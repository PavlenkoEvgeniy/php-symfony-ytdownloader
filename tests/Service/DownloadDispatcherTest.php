<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Message\DownloadMessage;
use App\Service\DownloadDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DownloadDispatcherTest extends TestCase
{
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

        $dispatcher = new DownloadDispatcher($em, $bus);
        $task       = $dispatcher->dispatch('https://youtube.com/watch?v=test', 'best', '42');

        $this->assertSame('https://youtube.com/watch?v=test', $task->getUrl());
        $this->assertSame('best', $task->getQuality());
        $this->assertSame(DownloadTask::STATUS_QUEUED, $task->getStatus());
        $this->assertSame('https://youtube.com/watch?v=test', $dispatched->getUrl());
        $this->assertSame('best', $dispatched->getQuality());
        $this->assertSame('42', $dispatched->getTelegramUserId());
        $this->assertSame($task->getId(), $dispatched->getTaskId());
    }
}
