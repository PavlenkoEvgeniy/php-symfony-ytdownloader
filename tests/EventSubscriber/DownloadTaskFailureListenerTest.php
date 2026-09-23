<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\DownloadTask;
use App\EventSubscriber\DownloadTaskFailureListener;
use App\Message\DownloadMessage;
use App\Service\DownloadTaskManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class DownloadTaskFailureListenerTest extends TestCase
{
    public function testMarksTaskAsErroredWhenNoRetryIsPlanned(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://example.com')->setQuality('best');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($task);
        $em->expects($this->once())->method('flush');

        $listener = new DownloadTaskFailureListener(new DownloadTaskManager($em), $this->createStub(\Psr\Log\LoggerInterface::class));

        $event = new WorkerMessageFailedEvent(
            new Envelope(new DownloadMessage('https://example.com', 'best', '', 5)),
            'async',
            new \RuntimeException('download failed')
        );

        $listener->onMessageFailed($event);

        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatus());
    }

    public function testDoesNothingWhileRetriesArePlanned(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('find');
        $em->expects($this->never())->method('flush');

        $listener = new DownloadTaskFailureListener(new DownloadTaskManager($em), $this->createStub(\Psr\Log\LoggerInterface::class));

        $event = new WorkerMessageFailedEvent(
            new Envelope(new DownloadMessage('https://example.com', 'best', '', 5)),
            'async',
            new \RuntimeException('temporary failure')
        );
        $event->setForRetry();

        $listener->onMessageFailed($event);
    }

    public function testIgnoresMessagesWithoutTaskId(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('find');

        $listener = new DownloadTaskFailureListener(new DownloadTaskManager($em), $this->createStub(\Psr\Log\LoggerInterface::class));

        $event = new WorkerMessageFailedEvent(
            new Envelope(new DownloadMessage('https://example.com', 'best')),
            'async',
            new \RuntimeException('download failed')
        );

        $listener->onMessageFailed($event);
    }
}
