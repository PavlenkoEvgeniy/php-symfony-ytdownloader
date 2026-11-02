<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\DownloadQuality;
use BotMan\BotMan\BotMan;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class TelegramMessageHandler implements TelegramMessageHandlerInterface
{
    public function __construct(
        private DownloadDispatcherInterface $downloadDispatcher,
        private TranslatorInterface $translator,
    ) {
    }

    #[\Override]
    public function onStart(BotMan $bot): void
    {
        $bot->reply($this->translator->trans('telegram.please_send_link'));
    }

    #[\Override]
    public function onUrl(BotMan $bot, string $url): void
    {
        $userId = (string) $bot->getUser()->getId();

        $this->downloadDispatcher->dispatch($url, DownloadQuality::BEST->value, $userId);

        $bot->reply($this->translator->trans('telegram.downloading_in_progress'));
    }
}
