<?php

declare(strict_types=1);

namespace App\Tests\Redis;

use App\Redis\RedisConnectionFactory;
use PHPUnit\Framework\TestCase;

final class RedisConnectionFactoryTest extends TestCase
{
    /**
     * Verifies a valid Redis URL produces a native Redis connection.
     */
    public function testCreateReturnsRedisConnection(): void
    {
        $password = \getenv('REDIS_PASSWORD');

        if (false === $password || '' === $password) {
            $this->markTestSkipped('REDIS_PASSWORD is not available in the test environment.');
        }

        $factory = new RedisConnectionFactory(\sprintf('redis://:%s@redis:6379', $password));

        $factory->create();

        $this->addToAssertionCount(1);
    }

    /**
     * Verifies a non-Redis connection is rejected.
     */
    public function testAsRedisRejectsForeignConnections(): void
    {
        $factory = new RedisConnectionFactory('redis://localhost');
        $method  = new \ReflectionMethod($factory, 'asRedis');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Redis connection is not a Redis instance.');

        $method->invoke($factory, new \stdClass());
    }
}
