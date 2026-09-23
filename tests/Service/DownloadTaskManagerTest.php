<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Service\DownloadTaskManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DownloadTaskManagerTest extends TestCase
{
    public function testMarkProcessingSetsStatus(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://example.com')->setQuality('best');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('find')
            ->with(DownloadTask::class, 1)
            ->willReturn($task);
        $em->expects($this->once())->method('flush');

        (new DownloadTaskManager($em))->markProcessing(1);

        $this->assertSame(DownloadTask::STATUS_PROCESSING, $task->getStatus());
    }

    public function testMarkSuccessSetsStatus(): void
    {
        $task = new DownloadTask();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($task);

        (new DownloadTaskManager($em))->markSuccess(1);

        $this->assertSame(DownloadTask::STATUS_SUCCESS, $task->getStatus());
    }

    public function testMarkErrorSetsStatus(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://example.com')->setQuality('best');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($task);

        (new DownloadTaskManager($em))->markError(1);

        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatus());
    }

    public function testMarkStatusIgnoresMissingTask(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('find')->willReturn(null);
        $em->expects($this->never())->method('flush');

        (new DownloadTaskManager($em))->markError(99);

        $this->assertSame(DownloadTask::STATUS_QUEUED, (new DownloadTask())->getStatus());
    }
}
