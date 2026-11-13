<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Dto\DownloadResult;
use App\Entity\DownloadTask;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Message\DownloadMessage;
use App\Message\TelegramDeliveryMessage;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\DownloadTaskManagerInterface;
use App\Service\TaskProcessingLockInterface;
use App\Service\TelegramNotifierInterface;
use App\Service\TelegramUpdateHandlerInterface;
use App\Service\VideoProcessorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class DownloadMessageHandler
{
    private const int MAX_ATTEMPTS             = 3;
    private const int RETRY_BASE_DELAY_SECONDS = 60;

    public function __construct(
        private VideoProcessorInterface $videoProcessor,
        private DownloadTaskManagerInterface $downloadTaskManager,
        private AuditLoggerInterface $auditLogger,
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
        private MessageBusInterface $messageBus,
        private TaskProcessingLockInterface $taskProcessingLock,
        private TelegramNotifierInterface $telegramNotifier,
        private TelegramUpdateHandlerInterface $telegramUpdateHandler,
    ) {
    }

    public function __invoke(DownloadMessage $message): void
    {
        $taskId = $message->taskId;
        $lease  = null;

        if (null !== $taskId) {
            // A held lease means another attempt is live; the message is a duplicate.
            $lease = $this->taskProcessingLock->tryAcquire($taskId);

            if (null === $lease) {
                return;
            }
        }

        try {
            if (null !== $taskId && !$this->downloadTaskManager->claimProcessing($taskId)) {
                return;
            }

            $result = $this->videoProcessor->process(
                $message->url,
                $message->quality,
                $message->telegramUserId,
                $taskId,
            );

            $task      = null !== $taskId ? $this->downloadTaskRepository->findById($taskId) : null;
            $willRetry = $this->shouldRetry($task, $result);

            if (null !== $taskId) {
                if ($result->isSuccess()) {
                    $this->downloadTaskManager->markSuccess($taskId);
                } elseif ($willRetry) {
                    $this->scheduleRetry($message, $taskId, $result, $task?->getAttempts() ?? 1);
                } else {
                    $this->downloadTaskManager->markError($taskId, $result->errorMessage);
                }
            }

            $isTelegram = '' !== $message->telegramUserId;

            if ($task instanceof DownloadTask && $result->isSuccess() && $task->isTelegramOrigin()) {
                $this->downloadTaskManager->markDeliveryStatus($task->getIdOrFail(), DownloadTask::DELIVERY_PENDING);
                $this->messageBus->dispatch(new TelegramDeliveryMessage($task->getIdOrFail()));
            }

            if (!$result->isSuccess() && !$willRetry && $isTelegram && $task instanceof DownloadTask) {
                $this->notifyFailure($task, (int) $message->telegramUserId, $message->taskId ?? 0, $result->isSizeExceeded());
            }

            $this->audit($message, $taskId, $task, $result, $willRetry, $isTelegram);
        } finally {
            $lease?->release();
        }
    }

    private function shouldRetry(?DownloadTask $task, DownloadResult $result): bool
    {
        return $result->isTransient()
            && $task instanceof DownloadTask
            && $task->getAttempts() < self::MAX_ATTEMPTS;
    }

    private function scheduleRetry(DownloadMessage $message, int $taskId, DownloadResult $result, int $attempts): void
    {
        $multiplier   = (int) (2 ** \max(0, $attempts - 1));
        $delaySeconds = \min(self::RETRY_BASE_DELAY_SECONDS * $multiplier, 3600);

        // Dispatch first: if the worker dies before the status update, the task stays
        // processing and the delayed message can still reclaim it. The reverse order
        // could leave a queued task with no message at all.
        $this->messageBus->dispatch(
            new DownloadMessage($message->url, $message->quality, $message->telegramUserId, $taskId),
            [new DelayStamp($delaySeconds * 1000)],
        );

        $this->downloadTaskManager->markRetryScheduled($taskId, $result->errorMessage ?? 'Download failed.');
    }

    private function notifyFailure(DownloadTask $task, int $fallbackChatId, int $taskId, bool $sizeExceeded): void
    {
        $chatId = $task->getTelegramChatId() ?? $fallbackChatId;

        if ($chatId <= 0) {
            return;
        }

        if ($sizeExceeded) {
            $this->telegramNotifier->notifyFileTooLarge((string) $chatId);

            // In a private chat the chat id and the Telegram user id coincide,
            // which is what the pending selection token is validated against.
            $this->telegramUpdateHandler->offerQualitySelection(
                $chatId,
                $chatId,
                $task->getUrlOrFail(),
                $task->getTitle() ?? $task->getUrlOrFail(),
                $task->getQualityOrFail(),
            );

            return;
        }

        if ($taskId <= 0) {
            return;
        }

        $this->telegramNotifier->notifyDownloadFailure((string) $chatId, $taskId);
    }

    private function audit(
        DownloadMessage $message,
        ?int $taskId,
        ?DownloadTask $task,
        DownloadResult $result,
        bool $willRetry,
        bool $isTelegram,
    ): void {
        $actor    = $isTelegram && $task instanceof DownloadTask ? $task->getUser() : null;
        $metadata = ['quality' => $message->quality, 'will_retry' => $willRetry];

        if (!$result->isSuccess()) {
            $metadata['reason'] = $result->errorMessage;
        }

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
            action: $result->isSuccess() ? AuditAction::DOWNLOAD_SUCCESS : AuditAction::DOWNLOAD_FAILURE,
            channel: $isTelegram ? AuditChannel::TELEGRAM : AuditChannel::SYSTEM,
            targetType: 'download_task',
            targetId: $taskId,
            targetLabel: $message->url,
            success: $result->isSuccess(),
            metadata: $metadata,
            actorType: $actorType,
            actorLabel: $actorLabel,
            actor: $actor,
        );
    }
}
