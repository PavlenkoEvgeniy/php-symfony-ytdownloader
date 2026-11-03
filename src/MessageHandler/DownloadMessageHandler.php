<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\DownloadTask;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Enum\DownloadOutcome;
use App\Message\DownloadMessage;
use App\Message\TelegramDeliveryMessage;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\DownloadTaskManagerInterface;
use App\Service\VideoProcessorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class DownloadMessageHandler
{
    public function __construct(
        private VideoProcessorInterface $videoProcessor,
        private DownloadTaskManagerInterface $downloadTaskManager,
        private AuditLoggerInterface $auditLogger,
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
        private MessageBusInterface $messageBus,
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

        $outcome = $this->videoProcessor->process(
            $message->url,
            $message->quality,
            $message->telegramUserId,
            $taskId,
        );

        if (null !== $taskId) {
            if (DownloadOutcome::SUCCESS === $outcome) {
                $this->downloadTaskManager->markSuccess($taskId);
            } else {
                $this->downloadTaskManager->markError($taskId);
            }
        }

        if (null !== $taskId && DownloadOutcome::SUCCESS === $outcome) {
            $task = $this->downloadTaskRepository->findById($taskId);

            if ($task instanceof DownloadTask && $task->isTelegramOrigin()) {
                $this->downloadTaskManager->markDeliveryStatus($taskId, DownloadTask::DELIVERY_PENDING);
                $this->messageBus->dispatch(new TelegramDeliveryMessage($taskId));
            }
        }

        $isTelegram = '' !== $message->telegramUserId;

        $this->auditLogger->log(
            action: DownloadOutcome::SUCCESS === $outcome ? AuditAction::DOWNLOAD_SUCCESS : AuditAction::DOWNLOAD_FAILURE,
            channel: $isTelegram ? AuditChannel::TELEGRAM : AuditChannel::SYSTEM,
            targetType: 'download_task',
            targetId: $taskId,
            targetLabel: $message->url,
            success: DownloadOutcome::SUCCESS === $outcome,
            metadata: ['quality' => $message->quality],
            actorType: $isTelegram ? AuditActorType::TELEGRAM : AuditActorType::SYSTEM,
            actorLabel: $isTelegram ? $message->telegramUserId : null,
        );
    }
}
