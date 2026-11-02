<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class ApiExceptionListenerTest extends TestCase
{
    /**
     * Verifies API exceptions become problem details without internal information.
     */
    public function testApiExceptionBecomesProblemDetailsWithoutInternals(): void
    {
        $event = $this->createEvent('/api/v1/test', new \RuntimeException('boom'));

        ($this->createListener())($event);

        $response = $event->getResponse();
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringStartsWith('application/problem+json', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $payload = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('about:blank', $payload['type']);
        $this->assertSame('Internal Server Error', $payload['title']);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $payload['status']);
        $this->assertSame('/api/v1/test', $payload['instance']);
        $this->assertArrayNotHasKey('trace', $payload);
        $this->assertArrayNotHasKey('class', $payload);
    }

    /**
     * Verifies non-API requests are left to the default error handler.
     */
    public function testNonApiExceptionIsLeftAlone(): void
    {
        $event = $this->createEvent('/health', new \RuntimeException('boom'));

        ($this->createListener())($event);

        $this->assertNull($event->getResponse());
    }

    private function createEvent(string $path, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }

    private function createListener(): ApiExceptionListener
    {
        return new ApiExceptionListener(new Serializer([new ObjectNormalizer()], [new JsonEncoder()]));
    }
}
