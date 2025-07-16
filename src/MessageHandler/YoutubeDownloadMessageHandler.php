<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\YoutubeDownloadMessage;
use App\Service\VideoDownloadService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class YoutubeDownloadMessageHandler
{
    public function __construct(private VideoDownloadService $processYoutubeVideo)
    {
    }

    public function __invoke(YoutubeDownloadMessage $message): void
    {
        $this->processYoutubeVideo->process($message->getUrl(), $message->getQuality(), $message->getTelegramUserId());
    }
}
