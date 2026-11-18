<?php

declare(strict_types=1);

namespace App\Controller\Telegram;

use App\Message\TelegramUpdateMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Confirms a Telegram update at once: probing a link and replying happen in
 * the background worker. A synchronous handler outlived Telegram's webhook
 * window and every timeout redelivered the update, repeating its side
 * effects and starving the updates queued behind it.
 */
#[Route('/telegram/hook', name: 'telegram_hook', methods: [Request::METHOD_POST])]
final class HookController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
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
            $this->messageBus->dispatch(new TelegramUpdateMessage($update));
        } catch (\Throwable $exception) {
            // Nothing was accepted: an error answer makes Telegram retry the
            // delivery instead of dropping the update.
            $this->logger->error('Failed to queue a Telegram update', ['error' => $exception->getMessage()]);

            return new Response('Internal Server Error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
