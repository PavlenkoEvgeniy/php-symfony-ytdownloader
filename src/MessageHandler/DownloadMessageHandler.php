<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DownloadMessage;
use App\Service\VideoDownloadService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DownloadMessageHandler
{
    public function __construct(private VideoDownloadService $processYoutubeVideo)
    {
    }

    public function __invoke(DownloadMessage $message): void
    {
        $this->processYoutubeVideo->process($message->getUrl(), $message->getQuality(), $message->getTelegramUserId());
    }
}
