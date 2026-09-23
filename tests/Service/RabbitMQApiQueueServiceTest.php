<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RabbitMQApiQueueService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class RabbitMQApiQueueServiceTest extends TestCase
{
    public function testGetQueueStatsViaApiReturnsNormalizedData(): void
    {
        $httpClient = new HttpClientStub(function (string $method, string $url, array $options): ResponseInterface {
            TestCase::assertSame('GET', $method);
            TestCase::assertSame('http://localhost:5672/api/queues/%2F/custom', $url);
            TestCase::assertSame(['auth_basic' => ['user', 'pass']], $options);

            return new ResponseStub([
                'messages_ready'          => 2,
                'messages_unacknowledged' => 3,
                'messages'                => 5,
                'consumers'               => 4,
                'state'                   => 'running',
                'memory'                  => 123,
            ]);
        });

        $service = new RabbitMQApiQueueService($httpClient, 'localhost', '5672', 'user', 'pass', '/');

        $expected = [
            'messages_ready'          => 2,
            'messages_unacknowledged' => 3,
            'messages_total'          => 5,
            'consumers'               => 4,
            'state'                   => 'running',
            'memory'                  => 123,
        ];

        $this->assertSame($expected, $service->getQueueStatsViaApi('custom'));
    }

    public function testGetQueueStatsViaApiThrowsRuntimeOnFailure(): void
    {
        $httpClient = new HttpClientStub(function (): ResponseInterface {
            throw new \RuntimeException('boom');
        });

        $service = new RabbitMQApiQueueService($httpClient, 'localhost', '5672', 'user', 'pass');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RabbitMQ API error: boom');

        $service->getQueueStatsViaApi('custom');
    }

    public function testGetProcessingMessagesCountReturnsValueFromStats(): void
    {
        $httpClient = new HttpClientStub(function (): ResponseInterface {
            return new ResponseStub([
                'messages_ready'          => 1,
                'messages_unacknowledged' => 9,
                'messages'                => 10,
                'consumers'               => 2,
                'state'                   => 'running',
                'memory'                  => 100,
            ]);
        });

        $service = new RabbitMQApiQueueService($httpClient, 'localhost', '5672', 'user', 'pass');

        $this->assertSame(9, $service->getProcessingMessagesCount());
    }

    public function testGetProcessingMessagesCountReturnsZeroOnFailure(): void
    {
        $httpClient = new HttpClientStub(function (): ResponseInterface {
            throw new \RuntimeException('fail');
        });

        $service = new RabbitMQApiQueueService($httpClient, 'localhost', '5672', 'user', 'pass');

        $this->assertSame(0, $service->getProcessingMessagesCount());
    }
}

/**
 * Lightweight HTTP client stub that allows per-request callbacks.
 */
final class HttpClientStub implements HttpClientInterface
{
    /**
     * @param \Closure(string, string, array<string, mixed>): ResponseInterface $callback
     */
    public function __construct(private readonly \Closure $callback)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return ($this->callback)($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        throw new \LogicException('Not implemented for this test stub.');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        return $this;
    }
}

/**
 * Minimal HTTP response stub.
 */
final class ResponseStub implements ResponseInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data, private int $statusCode = 200)
    {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(bool $throw = true): array
    {
        return [];
    }

    public function getContent(bool $throw = true): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $throw = true): array
    {
        return $this->data;
    }

    public function cancel(): void
    {
    }

    public function getInfo(?string $type = null): mixed
    {
        return null;
    }
}
