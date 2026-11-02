<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Log;
use App\Exception\PropertyIsNotSetException;
use PHPUnit\Framework\TestCase;

final class LogTest extends TestCase
{
    /**
     * Verifies a new log receives a creation timestamp.
     */
    public function testLogConstructorInitializesCreatedAt(): void
    {
        $log = new Log();

        $this->assertInstanceOf(\DateTimeImmutable::class, $log->getCreatedAt());
    }

    /**
     * Verifies every log setter returns the same instance.
     */
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

    /**
     * Verifies log getters return the values assigned to the entity.
     */
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

    /**
     * Verifies a new log has no identifier before persistence.
     */
    public function testLogIdIsNullByDefault(): void
    {
        $log = new Log();

        $this->assertNull($log->getId());
    }

    /**
     * Verifies a new log has no size value.
     */
    public function testLogSizeIsNullableByDefault(): void
    {
        $log = new Log();

        $this->assertNull($log->getSize());
    }

    /**
     * Verifies type, message and size are null before being set.
     */
    public function testLogPropertiesAreNullBeforeSet(): void
    {
        $log = new Log();

        $this->assertNull($log->getType());
        $this->assertNull($log->getMessage());
        $this->assertNull($log->getSize());
    }

    /**
     * Verifies a zero size is stored unchanged.
     */
    public function testLogCanStoreZeroSize(): void
    {
        $log = new Log();
        $log->setSize(0.0);

        $this->assertSame(0.0, $log->getSize());
    }

    /**
     * Verifies a negative size is stored unchanged.
     */
    public function testLogCanStoreNegativeSize(): void
    {
        $log = new Log();
        $log->setSize(-1.5);

        $this->assertSame(-1.5, $log->getSize());
    }

    /**
     * Verifies a large size is stored without precision loss.
     */
    public function testLogCanStoreLargeSize(): void
    {
        $log       = new Log();
        $largeSize = 1024.0 * 1024.0 * 1024.0 * 100; // 100GB

        $log->setSize($largeSize);

        $this->assertSame($largeSize, $log->getSize());
    }

    /**
     * Verifies the "or fail" getters return assigned values.
     */
    public function testOrFailGettersReturnAssignedValues(): void
    {
        $now = new \DateTimeImmutable();

        $log = new Log();

        $log->setType('success')
            ->setMessage('Download completed')
            ->setCreatedAt($now)
        ;

        $this->assertSame('success', $log->getTypeOrFail());
        $this->assertSame('Download completed', $log->getMessageOrFail());
        $this->assertSame($now, $log->getCreatedAtOrFail());
    }

    /**
     * Verifies the identifier "or fail" getter throws before persistence.
     */
    public function testIdOrFailThrowsForUnsavedLog(): void
    {
        $this->expectException(PropertyIsNotSetException::class);

        new Log()->getIdOrFail();
    }
}
