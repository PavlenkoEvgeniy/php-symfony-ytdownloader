<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\DownloadTask;
use App\Entity\Source;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Message\TelegramDeliveryMessage;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Repository\SourceRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\DownloadTaskManagerInterface;
use App\Service\TelegramClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TelegramDeliveryMessageHandler
{
    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
        private SourceRepositoryInterface $sourceRepository,
        private TelegramClientInterface $telegramClient,
        private DownloadTaskManagerInterface $downloadTaskManager,
        private EntityManagerInterface $entityManager,
        private AuditLoggerInterface $auditLogger,
        private LoggerInterface $logger,
        #[Autowire(env: 'int:TELEGRAM_MAX_UPLOAD_MB')]
        private int $telegramMaxUploadMb,
        #[Autowire(env: 'string:TELEGRAM_HOST_URL')]
        private string $telegramHostUrl,
    ) {
    }

    public function __invoke(TelegramDeliveryMessage $message): void
    {
        $task = $this->downloadTaskRepository->findById($message->taskId);

        if (!$task instanceof DownloadTask || !$task->isTelegramOrigin()) {
            return;
        }

        if (DownloadTask::DELIVERY_SENT === $task->getDeliveryStatus()) {
            return;
        }

        $user   = $task->getUser();
        $chatId = $task->getTelegramChatId();

        if (!$user instanceof User || !$user->isTelegramLinked() || null === $chatId) {
            $this->downloadTaskManager->markDeliveryStatus($task->getIdOrFail(), DownloadTask::DELIVERY_SKIPPED);

            return;
        }

        $sources = $this->sourceRepository->findByDownloadTask($task);

        if ([] === $sources) {
            $this->downloadTaskManager->markDeliveryStatus($task->getIdOrFail(), DownloadTask::DELIVERY_FAILED);
            $this->notifyFailure($chatId, 'The download finished, but no file was produced.');

            return;
        }

        $sent          = 0;
        $failed        = 0;
        $lastMessageId = null;

        foreach ($sources as $source) {
            $path = \sprintf('%s/%s', $source->getFilepathOrFail(), $source->getFilenameOrFail());

            if (!\is_file($path)) {
                ++$failed;
                $this->logger->error('Telegram delivery file is missing', ['path' => $path]);

                continue;
            }

            $size = (int) \filesize($path);

            if ($size > $this->telegramMaxUploadMb * 1024 * 1024) {
                ++$failed;
                $this->logger->warning('Telegram delivery file is too large', [
                    'path'  => $path,
                    'size'  => $size,
                    'limit' => $this->telegramMaxUploadMb,
                ]);

                continue;
            }

            try {
                $lastMessageId = $this->sendWithRetries($chatId, $path, $this->caption($task, $source));
                $this->deleteSource($source, $path, $user, $chatId);
                ++$sent;
            } catch (\Throwable $exception) {
                ++$failed;
                $this->logger->error('Telegram delivery failed', [
                    'path'  => $path,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->entityManager->flush();

        $status = 0 === $failed && $sent > 0 ? DownloadTask::DELIVERY_SENT : DownloadTask::DELIVERY_FAILED;

        $this->downloadTaskManager->markDeliveryStatus($task->getIdOrFail(), $status, $lastMessageId);

        $this->auditLogger->log(
            action: DownloadTask::DELIVERY_SENT === $status ? AuditAction::TELEGRAM_DELIVERED : AuditAction::TELEGRAM_DELIVERY_FAILED,
            channel: AuditChannel::TELEGRAM,
            targetType: 'download_task',
            targetId: $task->getIdOrFail(),
            targetLabel: $task->getUrlOrFail(),
            success: DownloadTask::DELIVERY_SENT === $status,
            metadata: ['sent' => $sent, 'failed' => $failed, 'telegram_chat_id' => $chatId],
            actor: $user,
        );

        if (DownloadTask::DELIVERY_FAILED === $status) {
            $this->notifyFailure(
                $chatId,
                'The download finished, but I could not send the file. You can download it from the library.',
            );
        }
    }

    private function sendWithRetries(int $chatId, string $path, string $caption): int
    {
        $attempt = 0;

        while (true) {
            ++$attempt;

            try {
                return $this->sendFile($chatId, $path, $caption);
            } catch (\Throwable $exception) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $exception;
                }

                \sleep(2 * $attempt);
            }
        }
    }

    private function sendFile(int $chatId, string $path, string $caption): int
    {
        $extension = \strtolower(\pathinfo($path, PATHINFO_EXTENSION));

        if ('mp3' === $extension) {
            return $this->telegramClient->sendAudio($chatId, $path, $caption);
        }

        if (\in_array($extension, ['mp4', 'webm', 'mkv'], true)) {
            try {
                return $this->telegramClient->sendVideo($chatId, $path, $caption);
            } catch (\Throwable) {
                // Some codecs are rejected by sendVideo; the document upload always works.
                return $this->telegramClient->sendDocument($chatId, $path, $caption);
            }
        }

        return $this->telegramClient->sendDocument($chatId, $path, $caption);
    }

    private function deleteSource(Source $source, string $path, User $user, int $chatId): void
    {
        if (!@\unlink($path)) {
            $this->logger->error('Failed to delete a delivered file', ['path' => $path]);
        }

        $this->auditLogger->log(
            action: AuditAction::SOURCE_DELETE,
            channel: AuditChannel::TELEGRAM,
            targetType: 'source',
            targetId: $source->getId(),
            targetLabel: $source->getFilename(),
            metadata: ['reason' => 'telegram_delivery', 'telegram_chat_id' => $chatId],
            actor: $user,
        );

        $this->entityManager->remove($source);
    }

    private function caption(DownloadTask $task, Source $source): string
    {
        $title = $task->getTitle() ?? $source->getFilenameOrFail();

        return \sprintf('%s · %s', $title, $task->getQualityOrFail());
    }

    private function notifyFailure(int $chatId, string $text): void
    {
        $libraryUrl = \rtrim($this->telegramHostUrl, '/') . '/ui/source';

        try {
            $this->telegramClient->sendMessage($chatId, \sprintf("%s\n%s", $text, $libraryUrl));
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send a Telegram delivery failure message', ['error' => $exception->getMessage()]);
        }
    }
}
