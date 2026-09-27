<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DownloadMessage;
use App\Service\DownloadTaskManager;
use App\Service\VideoProcessorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DownloadMessageHandler
{
    public function __construct(
        private VideoProcessorInterface $processYoutubeVideo,
        private DownloadTaskManager $taskManager,
    ) {
    }

    public function __invoke(DownloadMessage $message): void
    {
        $taskId = $message->getTaskId();

        // Messages dispatched before the task tracking was introduced have no task row.
        if (null !== $taskId) {
            // A failed claim means the task is gone, already processing or already
            // finished: the message is a duplicate (e.g. redelivered after a reset).
            if (!$this->taskManager->claimProcessing($taskId)) {
                return;
            }
        }

        $this->processYoutubeVideo->process($message->getUrl(), $message->getQuality(), $message->getTelegramUserId());

        if (null !== $taskId) {
            $this->taskManager->markSuccess($taskId);
        }
    }
}
