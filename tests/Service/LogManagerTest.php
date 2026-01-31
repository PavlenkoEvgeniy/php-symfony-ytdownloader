<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Log;
use App\Service\LogManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class LogManagerTest extends TestCase
{
    public function testCreatePersistsLogWithOptionalSize(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Log $log): bool {
                return 'info' === $log->getType()
                    && 'hello world' === $log->getMessage()
                    && 12.5 === $log->getSize();
            }));

        $manager = new LogManager($em);

        $log = $manager->create('info', 'hello world', 12.5);

        $this->assertSame('info', $log->getType());
        $this->assertSame('hello world', $log->getMessage());
        $this->assertSame(12.5, $log->getSize());
    }

    public function testFlushDelegatesToEntityManager(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $manager = new LogManager($em);
        $manager->flush();
    }
}
