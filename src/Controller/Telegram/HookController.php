<?php

declare(strict_types=1);

namespace App\Controller\Telegram;

use App\Service\TelegramUpdateHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/telegram/hook', name: 'telegram_hook', methods: [Request::METHOD_POST])]
final class HookController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TelegramUpdateHandlerInterface $telegramUpdateHandler,
        #[Autowire(env: 'bool:TELEGRAM_BOT_ENABLED')]
        private readonly bool $telegramBotEnabled,
        #[Autowire(env: 'string:TELEGRAM_WEBHOOK_SECRET')]
        private readonly string $telegramWebhookSecret,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (false === $this->telegramBotEnabled) {
            return new Response('Disabled', Response::HTTP_FORBIDDEN);
        }

        $secret = (string) $request->headers->get('X-Telegram-Bot-Api-Secret-Token');

        if ('' !== $this->telegramWebhookSecret && !\hash_equals($this->telegramWebhookSecret, $secret)) {
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        try {
            $update = $request->toArray();
        } catch (\Throwable) {
            return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->telegramUpdateHandler->handle($update);
        } catch (\Throwable $exception) {
            // Return 200 so Telegram does not redeliver an update after a partial side effect.
            $this->logger->error('Failed to handle a Telegram update', ['error' => $exception->getMessage()]);
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
