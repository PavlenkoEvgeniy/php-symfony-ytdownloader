<?php

declare(strict_types=1);

namespace App\Tests\Controller\Telegram;

use App\Controller\Telegram\HookController;
use App\Message\TelegramUpdateMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

final class HookControllerTest extends TestCase
{
    /**
     * Verifies the hook is rejected when the bot is disabled.
     */
    public function testReturnsForbiddenWhenDisabled(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $response = $this->controller($bus, enabled: false)(new Request());

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame('Disabled', $response->getContent());
    }

    /**
     * Verifies a request with a wrong webhook secret is rejected.
     */
    public function testReturnsForbiddenWhenSecretIsWrong(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $request = $this->jsonRequest(['message' => ['text' => 'hello']]);
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'wrong');

        $response = $this->controller($bus, secret: 'expected')($request);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * Verifies a valid update is queued for the worker and acknowledged at once.
     */
    public function testQueuesAnUpdateAndAcknowledges(): void
    {
        $update = ['update_id' => 7, 'message' => ['text' => 'hello']];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $message): bool => $message instanceof TelegramUpdateMessage && $message->update === ['update_id' => 7, 'message' => ['text' => 'hello']],
            ));

        $request = $this->jsonRequest($update);
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'secret');

        $response = $this->controller($bus, secret: 'secret')($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    /**
     * Verifies malformed JSON is rejected.
     */
    public function testReturnsBadRequestForInvalidJson(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $request = $this->jsonRequest('{invalid');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'secret');

        $response = $this->controller($bus, secret: 'secret')($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * Verifies a dispatch failure is logged and answered with 500 so Telegram retries.
     */
    public function testQueueFailuresAreLoggedAndAnsweredWithServerError(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $request = $this->jsonRequest(['message' => ['text' => 'hello']]);
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'secret');

        $response = (new HookController($logger, $bus, true, 'secret'))($request);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    private function controller(
        MessageBusInterface $bus,
        bool $enabled = true,
        string $secret = '',
    ): HookController {
        return new HookController($this->createStub(LoggerInterface::class), $bus, $enabled, $secret);
    }

    /**
     * @param array<string, mixed>|string $payload
     */
    private function jsonRequest(array|string $payload): Request
    {
        return Request::create(
            '/telegram/hook',
            Request::METHOD_POST,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \is_string($payload) ? $payload : (string) \json_encode($payload),
        );
    }
}
