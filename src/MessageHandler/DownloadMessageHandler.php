<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\DownloadTask;
use App\Entity\User;
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

        $isTelegram = '' !== $message->telegramUserId;
        $task       = null !== $taskId && $isTelegram ? $this->downloadTaskRepository->findById($taskId) : null;

        if ($task instanceof DownloadTask && DownloadOutcome::SUCCESS === $outcome && $task->isTelegramOrigin()) {
            $this->downloadTaskManager->markDeliveryStatus($task->getIdOrFail(), DownloadTask::DELIVERY_PENDING);
            $this->messageBus->dispatch(new TelegramDeliveryMessage($task->getIdOrFail()));
        }

        $actor    = $isTelegram && $task instanceof DownloadTask ? $task->getUser() : null;
        $metadata = ['quality' => $message->quality];

        if ($isTelegram) {
            $telegramChatId = $task instanceof DownloadTask
                ? $task->getTelegramChatId()
                : (int) $message->telegramUserId;

            if (null !== $telegramChatId) {
                $metadata['telegram_chat_id'] = $telegramChatId;
            }
        }

        if (!$isTelegram) {
            $actorType  = AuditActorType::SYSTEM;
            $actorLabel = null;
        } elseif ($actor instanceof User) {
            $actorType  = null;
            $actorLabel = null;
        } else {
            $actorType  = AuditActorType::TELEGRAM;
            $actorLabel = $message->telegramUserId;
        }

        $this->auditLogger->log(
            action: DownloadOutcome::SUCCESS === $outcome ? AuditAction::DOWNLOAD_SUCCESS : AuditAction::DOWNLOAD_FAILURE,
            channel: $isTelegram ? AuditChannel::TELEGRAM : AuditChannel::SYSTEM,
            targetType: 'download_task',
            targetId: $taskId,
            targetLabel: $message->url,
            success: DownloadOutcome::SUCCESS === $outcome,
            metadata: $metadata,
            actorType: $actorType,
            actorLabel: $actorLabel,
            actor: $actor,
        );
    }
}
