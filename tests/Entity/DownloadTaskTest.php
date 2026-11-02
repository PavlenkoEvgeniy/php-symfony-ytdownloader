<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DownloadTask;
use App\Exception\PropertyIsNotSetException;
use PHPUnit\Framework\TestCase;

final class DownloadTaskTest extends TestCase
{
    /**
     * Verifies a new task starts queued and empty.
     */
    public function testDefaults(): void
    {
        $task = new DownloadTask();

        $this->assertNull($task->getId());
        $this->assertNull($task->getUrl());
        $this->assertNull($task->getQuality());
        $this->assertSame(DownloadTask::STATUS_QUEUED, $task->getStatus());
        $this->assertNull($task->getErrorMessage());
        $this->assertSame(0, $task->getAttempts());
        $this->assertNull($task->getStartedAt());
        $this->assertNull($task->getFinishedAt());
    }

    /**
     * Verifies every setter returns the same instance.
     */
    public function testSettersReturnInstance(): void
    {
        $task = new DownloadTask();

        $this->assertSame($task, $task->setUrl('https://test.local'));
        $this->assertSame($task, $task->setQuality('best'));
        $this->assertSame($task, $task->setStatus(DownloadTask::STATUS_PROCESSING));
        $this->assertSame($task, $task->setErrorMessage('error'));
        $this->assertSame($task, $task->setAttempts(2));
        $this->assertSame($task, $task->setStartedAt(new \DateTimeImmutable()));
        $this->assertSame($task, $task->setFinishedAt(new \DateTimeImmutable()));
    }

    /**
     * Verifies getters return the assigned values.
     */
    public function testGettersReturnAssignedValues(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://test.local')
            ->setQuality('moderate')
            ->setStatus(DownloadTask::STATUS_SUCCESS)
        ;

        $this->assertSame('https://test.local', $task->getUrl());
        $this->assertSame('moderate', $task->getQuality());
        $this->assertSame(DownloadTask::STATUS_SUCCESS, $task->getStatus());
        $this->assertNull($task->getErrorMessage());
        $this->assertSame(0, $task->getAttempts());
        $this->assertNull($task->getStartedAt());
        $this->assertNull($task->getFinishedAt());
    }

    /**
     * Verifies the "or fail" getters return assigned values.
     */
    public function testOrFailGettersReturnAssignedValues(): void
    {
        $now = new \DateTime();

        $task = new DownloadTask();

        $task->setUrl('https://test.local')
            ->setQuality('poor')
            ->setStatus(DownloadTask::STATUS_ERROR)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;

        $this->assertSame('https://test.local', $task->getUrlOrFail());
        $this->assertSame('poor', $task->getQualityOrFail());
        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatusOrFail());
        $this->assertSame($now, $task->getCreatedAtOrFail());
        $this->assertSame($now, $task->getUpdatedAtOrFail());
    }

    /**
     * Verifies the identifier "or fail" getter throws before persistence.
     */
    public function testIdOrFailThrowsForUnsavedTask(): void
    {
        $this->expectException(PropertyIsNotSetException::class);

        new DownloadTask()->getIdOrFail();
    }
}
