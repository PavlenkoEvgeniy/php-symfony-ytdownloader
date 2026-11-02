<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Message\DownloadMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DownloadDispatcher implements DownloadDispatcherInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    #[\Override]
    /**
     * Persists the task so it is visible as "queued" and only then
     * dispatches the message, so every dispatch has a matching task row.
     *
     * @throws ExceptionInterface
     */
    public function dispatch(string $url, string $quality, string $telegramUserId = ''): DownloadTask
    {
        $task = new DownloadTask()->setUrl($url)
            ->setQuality($quality)
        ;

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new DownloadMessage($url, $quality, $telegramUserId, $task->getId()));

        $this->auditLogger->log(
            action: AuditAction::DOWNLOAD_REQUEST,
            channel: '' !== $telegramUserId ? AuditChannel::TELEGRAM : null,
            targetType: 'download_task',
            targetId: $task->getId(),
            targetLabel: $url,
            metadata: ['quality' => $quality],
            actorType: '' !== $telegramUserId ? AuditActorType::TELEGRAM : null,
            actorLabel: '' !== $telegramUserId ? $telegramUserId : null,
        );

        return $task;
    }
}
