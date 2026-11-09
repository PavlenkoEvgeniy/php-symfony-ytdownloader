<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\DownloadTask;
use App\Message\DownloadMessage;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\DownloadTaskManagerInterface;
use App\Service\TelegramNotifierInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener]
final readonly class DownloadTaskFailureListener
{
    public function __construct(
        private DownloadTaskManagerInterface $downloadTaskManager,
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
        private TelegramNotifierInterface $telegramNotifier,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        try {
            $message = $event->getEnvelope()->getMessage();

            if ($message instanceof DownloadMessage && null !== $message->taskId) {
                $reason = $this->shortenReason($event->getThrowable()->getMessage());

                $this->downloadTaskManager->markError($message->taskId, $reason);
                $this->notifyTelegramFailure($message->taskId);
            }
        } catch (\Throwable $exception) {
            // Never break the worker itself: the failed message still reaches the failed transport.
            $this->logger->error('Failed to mark download task as errored', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyTelegramFailure(int $taskId): void
    {
        $task = $this->downloadTaskRepository->findById($taskId);

        if (!$task instanceof DownloadTask || !$task->isTelegramOrigin()) {
            return;
        }

        $chatId = $task->getTelegramChatId();

        if (null === $chatId) {
            return;
        }

        $this->telegramNotifier->notifyDownloadFailure((string) $chatId, $taskId);
    }

    private function shortenReason(string $reason): string
    {
        $reason = \trim($reason);

        return \mb_substr('' === $reason ? 'Download failed.' : $reason, 0, 500);
    }
}
