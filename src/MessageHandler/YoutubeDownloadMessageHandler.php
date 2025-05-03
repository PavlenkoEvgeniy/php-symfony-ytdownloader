<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\YoutubeDownloadMessage;
use App\Service\VideoDownloadService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class YoutubeDownloadMessageHandler
{
    public function __construct(private readonly VideoDownloadService $processYoutubeVideo)
    {
    }

    public function __invoke(YoutubeDownloadMessage $message)
    {
        $this->processYoutubeVideo->process($message->getContent());
    }
}
