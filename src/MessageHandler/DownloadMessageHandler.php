<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DownloadMessage;
use App\Service\VideoDownloaderService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DownloadMessageHandler
{
    public function __construct(private VideoDownloaderService $processYoutubeVideo)
    {
    }

    public function __invoke(DownloadMessage $message): void
    {
        $this->processYoutubeVideo->process($message->getUrl(), $message->getQuality(), $message->getTelegramUserId());
    }
}
