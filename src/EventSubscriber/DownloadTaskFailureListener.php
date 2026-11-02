<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Message\DownloadMessage;
use App\Service\DownloadTaskManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener]
final readonly class DownloadTaskFailureListener
{
    public function __construct(
        private DownloadTaskManagerInterface $downloadTaskManager,
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
                $this->downloadTaskManager->markError($message->taskId, $event->getThrowable()->getMessage());
            }
        } catch (\Throwable $exception) {
            // Never break the worker itself: the failed message still reaches the failed transport.
            $this->logger->error('Failed to mark download task as errored', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
