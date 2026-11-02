<?php

declare(strict_types=1);

namespace App\Tests\Controller\HealthCheck;

use App\Controller\HealthCheck\ReadyController;
use App\Redis\RedisConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ReadyControllerTest extends TestCase
{
    /**
     * Verifies readiness is OK when both dependencies answer.
     */
    public function testReadyWhenDependenciesAreAvailable(): void
    {
        $password = \getenv('REDIS_PASSWORD');

        if (false === $password || '' === $password) {
            $this->markTestSkipped('REDIS_PASSWORD is not available in the test environment.');
        }

        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($this->createStub(Result::class));

        $redis = new \Redis();
        $redis->connect('redis', 6379);
        $redis->auth($password);

        /** @phpstan-ignore-next-line */
        $factory = $this->createStub(RedisConnectionFactory::class);
        $factory->method('create')->willReturn($redis);

        $response = (new ReadyController($connection, $factory))->__invoke();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('{"status":"OK","checks":{"database":"OK","redis":"OK"}}', (string) $response->getContent());
    }

    /**
     * Verifies readiness fails when the database is unavailable.
     */
    public function testFailsWhenDatabaseIsUnavailable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(new \RuntimeException('database is down'));

        /** @phpstan-ignore-next-line */
        $factory = $this->createStub(RedisConnectionFactory::class);
        $factory->method('create')->willThrowException(new \RuntimeException('redis is not needed'));

        $response = (new ReadyController($connection, $factory))->__invoke();

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertStringContainsString('"database":"FAIL"', (string) $response->getContent());
    }

    /**
     * Verifies readiness fails when Redis is unavailable.
     */
    public function testFailsWhenRedisIsUnavailable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($this->createStub(Result::class));

        /** @phpstan-ignore-next-line */
        $factory = $this->createStub(RedisConnectionFactory::class);
        $factory->method('create')->willThrowException(new \RuntimeException('redis is down'));

        $response = (new ReadyController($connection, $factory))->__invoke();

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertStringContainsString('"redis":"FAIL"', (string) $response->getContent());
    }
}
