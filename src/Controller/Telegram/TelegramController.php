<?php

declare(strict_types=1);

namespace App\Controller\Telegram;

use App\Message\DownloadMessage;
use App\Service\TelegramBotService;
use BotMan\BotMan\BotMan;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class TelegramController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/telegram/hook', name: 'telegram_hook', methods: [Request::METHOD_POST])]
    public function download(
        LoggerInterface $logger,
        TelegramBotService $telegramBotService,
        bool $telegramBotEnabled,
    ): Response {
        if (false === $telegramBotEnabled) {
            return new Response('Disabled', Response::HTTP_FORBIDDEN);
        }

        $bot = $telegramBotService->getBot();
        $bot->hears('/start', function (BotMan $bot) {
            $bot->reply('Please send me a link for download');
        });

        $bot->hears('(https://.*)', function (BotMan $bot, string $url) {
            $quality = 'best'; // Default quality
            $userId  = (string) $bot->getUser()->getId();
            $this->bus->dispatch(new DownloadMessage($url, $quality, $userId));

            $bot->reply('Downloading is in progress. Please wait...');
        });

        $bot->listen();

        $logger->info('Telegram hook received');

        return new Response('Dispatched', Response::HTTP_OK);
    }
}
