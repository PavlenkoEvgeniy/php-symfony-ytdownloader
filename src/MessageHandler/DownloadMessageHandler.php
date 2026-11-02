<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\DownloadOutcome;
use App\Message\DownloadMessage;
use App\Service\DownloadTaskManagerInterface;
use App\Service\VideoProcessorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DownloadMessageHandler
{
    public function __construct(
        private VideoProcessorInterface $videoProcessor,
        private DownloadTaskManagerInterface $downloadTaskManager,
    ) {
    }

    public function __invoke(DownloadMessage $message): void
    {
        $taskId = $message->taskId;

        // Messages dispatched before the task tracking was introduced have no task row.
        if (null !== $taskId) {
            // A failed claim means the task is gone, already processing or already
            // finished: the message is a duplicate (e.g. redelivered after a reset).
            if (!$this->downloadTaskManager->claimProcessing($taskId)) {
                return;
            }
        }

        $outcome = $this->videoProcessor->process($message->url, $message->quality, $message->telegramUserId);

        if (null !== $taskId) {
            if (DownloadOutcome::SUCCESS === $outcome) {
                $this->downloadTaskManager->markSuccess($taskId);
            } else {
                $this->downloadTaskManager->markError($taskId);
            }
        }
    }
}
