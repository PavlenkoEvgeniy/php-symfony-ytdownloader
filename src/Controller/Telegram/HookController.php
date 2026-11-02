<?php

declare(strict_types=1);

namespace App\Controller\Telegram;

use App\Service\TelegramBotServiceInterface;
use App\Service\TelegramMessageHandlerInterface;
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
        private readonly TelegramBotServiceInterface $telegramBotService,
        private readonly TelegramMessageHandlerInterface $telegramMessageHandler,
        #[Autowire(env: 'bool:TELEGRAM_BOT_ENABLED')]
        private readonly bool $telegramBotEnabled,
    ) {
    }

    public function __invoke(): Response
    {
        if (false === $this->telegramBotEnabled) {
            return new Response('Disabled', Response::HTTP_FORBIDDEN);
        }

        $bot = $this->telegramBotService->getBot();
        $bot->hears('/start', $this->telegramMessageHandler->onStart(...));
        $bot->hears('(https://.*)', $this->telegramMessageHandler->onUrl(...));

        $bot->listen();

        $this->logger->info('Telegram hook received');

        return new Response('Dispatched', Response::HTTP_OK);
    }
}
