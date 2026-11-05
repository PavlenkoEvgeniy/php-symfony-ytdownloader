<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Exception\DownloadIntakeDisabledException;
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
        private DownloadIntakePolicyInterface $intakePolicy,
    ) {
    }

    #[\Override]
    /**
     * Rejects the request when its surface may not create a task, persists the
     * task so it is visible as "queued", and only then dispatches the message,
     * so every dispatch has a matching task row.
     *
     * @throws ExceptionInterface
     * @throws DownloadIntakeDisabledException
     */
    public function dispatch(
        string $url,
        string $quality,
        ?User $user = null,
        string $origin = DownloadTask::ORIGIN_UI,
        ?int $telegramChatId = null,
        ?string $title = null,
    ): DownloadTask {
        $this->intakePolicy->assertAccepts($origin);

        $task = new DownloadTask()->setUrl($url)
            ->setQuality($quality)
            ->setUser($user)
            ->setOrigin($origin)
            ->setTelegramChatId($telegramChatId)
            ->setTitle($title)
        ;

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $telegramUserId = null === $telegramChatId ? '' : (string) $telegramChatId;

        $this->messageBus->dispatch(new DownloadMessage($url, $quality, $telegramUserId, $task->getId()));

        $isTelegram = DownloadTask::ORIGIN_TELEGRAM === $origin;

        $this->auditLogger->log(
            action: AuditAction::DOWNLOAD_REQUEST,
            channel: $isTelegram ? AuditChannel::TELEGRAM : null,
            targetType: 'download_task',
            targetId: $task->getId(),
            targetLabel: $url,
            metadata: ['quality' => $quality],
            actorType: $isTelegram ? AuditActorType::TELEGRAM : null,
            actorLabel: $isTelegram ? $telegramUserId : null,
        );

        return $task;
    }
}
