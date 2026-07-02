<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Log;
use PHPUnit\Framework\TestCase;

final class LogTest extends TestCase
{
    public function testLogConstructorInitializesCreatedAt(): void
    {
        $log = new Log();

        $this->assertInstanceOf(\DateTimeImmutable::class, $log->getCreatedAt());
    }

    public function testLogSettersReturnInstance(): void
    {
        $log = new Log();
        $now = new \DateTimeImmutable();

        $result1 = $log->setType('success');
        $result2 = $log->setMessage('Download completed');
        $result3 = $log->setCreatedAt($now);
        $result4 = $log->setSize(1024.5);

        $this->assertSame($log, $result1);
        $this->assertSame($log, $result2);
        $this->assertSame($log, $result3);
        $this->assertSame($log, $result4);
    }

    public function testLogGettersReturnSetValues(): void
    {
        $log = new Log();
        $now = new \DateTimeImmutable();

        $log->setType('success');
        $log->setMessage('Download completed');
        $log->setCreatedAt($now);
        $log->setSize(1024.5);

        $this->assertSame('success', $log->getType());
        $this->assertSame('Download completed', $log->getMessage());
        $this->assertSame($now, $log->getCreatedAt());
        $this->assertSame(1024.5, $log->getSize());
    }

    public function testLogIdIsNullByDefault(): void
    {
        $log = new Log();

        $this->assertNull($log->getId());
    }

    public function testLogSizeIsNullableByDefault(): void
    {
        $log = new Log();

        $this->assertNull($log->getSize());
    }

    public function testLogPropertiesAreNullBeforeSet(): void
    {
        $log = new Log();

        $this->assertNull($log->getType());
        $this->assertNull($log->getMessage());
        $this->assertNull($log->getSize());
    }

    public function testLogCanStoreZeroSize(): void
    {
        $log = new Log();
        $log->setSize(0.0);

        $this->assertSame(0.0, $log->getSize());
    }

    public function testLogCanStoreNegativeSize(): void
    {
        $log = new Log();
        $log->setSize(-1.5);

        $this->assertSame(-1.5, $log->getSize());
    }

    public function testLogCanStoreLargeSize(): void
    {
        $log       = new Log();
        $largeSize = 1024.0 * 1024.0 * 1024.0 * 100; // 100GB

        $log->setSize($largeSize);

        $this->assertSame($largeSize, $log->getSize());
    }
}
