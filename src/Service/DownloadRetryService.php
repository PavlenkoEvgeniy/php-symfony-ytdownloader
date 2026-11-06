<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Message\DownloadMessage;
use App\Repository\DownloadTaskRepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DownloadRetryService implements DownloadRetryServiceInterface
{
    public function __construct(
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
        private DownloadTaskManagerInterface $downloadTaskManager,
        private MessageBusInterface $messageBus,
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    #[\Override]
    public function reDownload(int $taskId, ?User $actor = null, ?AuditChannel $channel = null): bool
    {
        $task = $this->downloadTaskRepository->findById($taskId);

        if (!$task instanceof DownloadTask || DownloadTask::STATUS_ERROR !== $task->getStatus()) {
            return false;
        }

        if (!$this->downloadTaskManager->requeueError($taskId)) {
            return false;
        }

        $telegramUserId = $task->isTelegramOrigin() && null !== $task->getTelegramChatId()
            ? (string) $task->getTelegramChatId()
            : '';

        $this->messageBus->dispatch(new DownloadMessage(
            $task->getUrlOrFail(),
            $task->getQualityOrFail(),
            $telegramUserId,
            $taskId,
        ));

        $this->auditLogger->log(
            action: AuditAction::DOWNLOAD_RETRY,
            channel: $channel,
            targetType: 'download_task',
            targetId: $taskId,
            targetLabel: $task->getUrlOrFail(),
            metadata: [
                'quality' => $task->getQualityOrFail(),
                'origin'  => $task->getOrigin(),
            ],
            actor: $actor,
        );

        return true;
    }
}
