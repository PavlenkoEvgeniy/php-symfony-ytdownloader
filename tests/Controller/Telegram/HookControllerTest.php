<?php

declare(strict_types=1);

namespace App\Tests\Controller\Telegram;

use App\Controller\Telegram\HookController;
use App\Service\TelegramUpdateHandlerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HookControllerTest extends TestCase
{
    /**
     * Verifies the hook is rejected when the bot is disabled.
     */
    public function testReturnsForbiddenWhenDisabled(): void
    {
        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->controller($handler, enabled: false)(new Request());

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame('Disabled', $response->getContent());
    }

    /**
     * Verifies a request with a wrong webhook secret is rejected.
     */
    public function testReturnsForbiddenWhenSecretIsWrong(): void
    {
        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $request = $this->jsonRequest(['message' => ['text' => 'hello']]);
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'wrong');

        $response = $this->controller($handler, secret: 'expected')($request);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * Verifies a valid update is passed to the update handler.
     */
    public function testHandlesAnUpdate(): void
    {
        $update  = ['message' => ['text' => 'hello']];
        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($update);

        $request = $this->jsonRequest($update);
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'secret');

        $response = $this->controller($handler, secret: 'secret')($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    /**
     * Verifies malformed JSON is rejected.
     */
    public function testReturnsBadRequestForInvalidJson(): void
    {
        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $request = $this->jsonRequest('{invalid');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'secret');

        $response = $this->controller($handler, secret: 'secret')($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * Verifies handler failures are logged but Telegram still receives 200.
     */
    public function testLogsHandlerFailuresAndReturnsOk(): void
    {
        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->method('handle')->willThrowException(new \RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $request = $this->jsonRequest(['message' => ['text' => 'hello']]);
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'secret');

        $response = (new HookController($logger, $handler, true, 'secret'))($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function controller(
        TelegramUpdateHandlerInterface $handler,
        bool $enabled = true,
        string $secret = '',
    ): HookController {
        return new HookController($this->createStub(LoggerInterface::class), $handler, $enabled, $secret);
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
