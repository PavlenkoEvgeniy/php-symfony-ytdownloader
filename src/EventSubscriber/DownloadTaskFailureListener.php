<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Message\DownloadMessage;
use App\Service\DownloadTaskManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final readonly class DownloadTaskFailureListener implements EventSubscriberInterface
{
    public function __construct(
        private DownloadTaskManager $taskManager,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        try {
            $message = $event->getEnvelope()->getMessage();

            if ($message instanceof DownloadMessage && null !== $message->getTaskId()) {
                $this->taskManager->markError($message->getTaskId());
            }
        } catch (\Throwable $e) {
            // Never break the worker itself: the failed message still reaches the failed transport.
            $this->logger->error('Failed to mark download task as errored', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
