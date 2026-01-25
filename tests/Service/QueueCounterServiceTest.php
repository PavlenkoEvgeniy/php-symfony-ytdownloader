<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\QueueCounterService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class QueueCounterServiceTest extends TestCase
{
    public function testReturnsQueueCountForCustomQueue(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('SELECT COUNT(*)'), ['queueName' => 'custom'])
            ->willReturn('5');

        $service = new QueueCounterService($connection);

        $this->assertSame(5, $service->getQueueCount('custom'));
    }

    public function testReturnsQueueCountForDefaultQueue(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('SELECT COUNT(*)'), ['queueName' => 'default'])
            ->willReturn(0);

        $service = new QueueCounterService($connection);

        $this->assertSame(0, $service->getQueueCount());
    }
}
