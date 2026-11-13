<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\DownloadResult;
use App\Entity\DownloadTask;
use App\Entity\Source;
use App\Repository\DownloadTaskRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class VideoDownloaderService implements VideoProcessorInterface
{
    public function __construct(
        private YoutubeDlWrapperInterface $youtubeDlWrapper,
        private FormatResolverInterface $formatResolver,
        private SourceManagerInterface $sourceManager,
        private LogManagerInterface $logManager,
        private LoggerInterface $logger,
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
        private DownloadProgressNotifierInterface $progressNotifier,
        private DownloadFailureClassifierInterface $failureClassifier,
        private DownloadSizeEstimatorInterface $sizeEstimator,
        #[Autowire(env: 'int:TELEGRAM_MAX_UPLOAD_MB')]
        private int $telegramMaxUploadMb,
    ) {
    }

    #[\Override]
    public function process(
        string $videoUrl,
        string $format,
        ?string $telegramUserId = null,
        ?int $taskId = null,
    ): DownloadResult {
        $telegramUserId ??= '';
        $downloadTask    = null === $taskId ? null : $this->downloadTaskRepository->findById($taskId);
        $origin          = $downloadTask?->getOrigin();
        $allowPlaylist   = !($downloadTask?->isTelegramOrigin() ?? false);

        $this->logManager->create('commenced', 'Started downloading.');
        $this->logManager->flush();

        [$downloadFormat, $mergeAsVideo] = $this->formatResolver->resolve($format);

        $sizeRejection = $this->sizeRejection($downloadTask, $videoUrl, $downloadFormat, $allowPlaylist);

        if (null !== $sizeRejection) {
            $this->logManager->create('error', $sizeRejection);
            $this->logManager->flush();
            $this->logger->info('Rejected by the Telegram size guard', ['reason' => $sizeRejection]);

            return DownloadResult::sizeExceeded($sizeRejection);
        }

        $progressReporter = $this->progressReporter($downloadTask, $telegramUserId);
        $onProgress       = null === $progressReporter ? null : $progressReporter->report(...);

        $collection = $this->youtubeDlWrapper->download($videoUrl, $downloadFormat, $mergeAsVideo, $allowPlaylist, $onProgress);

        $saved      = 0;
        $firstError = null;

        foreach ($collection->getVideos() as $video) {
            if (null !== $video->getError()) {
                $firstError ??= $video->getError();

                $this->logger->error('Error during downloading', ['error' => $video->getError()]);

                $this->logManager->create('error', \sprintf('Error during downloading: %s', $video->getError()));
                $this->logManager->flush();

                continue;
            }

            $filename = $video->getFile()->getBasename();
            $path     = $video->getFile()->getPath();
            $size     = $video->getFile()->getSize();
            $source   = $this->sourceManager->findByFilename($filename);

            if (!$source instanceof Source) {
                $this->sourceManager->createFromDownloadedFile($filename, $path, $size, $downloadTask, $origin);

                $this->logManager->create('success', \sprintf('File "%s" downloaded successfully.', $filename), $size);
                $this->logger->info(\sprintf('File "%s" downloaded successfully.', $filename));
            } elseif ($downloadTask instanceof DownloadTask && $downloadTask->isTelegramOrigin()) {
                // The same file was downloaded before: hand it to the current task so it
                // can be delivered and deleted after the file is sent to Telegram.
                $source->setDownloadTask($downloadTask)->setOrigin($origin);
            }

            ++$saved;
        }

        if (0 === $saved && null === $firstError) {
            $firstError = 'Error during downloading: no files were downloaded.';
            $this->logManager->create('error', $firstError);
            $this->logManager->flush();
        }

        // Always flush: files already on disk must get their Source row even when
        // some playlist items failed, otherwise Prune would treat them as orphans.
        $this->sourceManager->flush();

        if ($saved > 0) {
            $this->logManager->create('finished', 'Finished downloading.');
            $this->logManager->flush();

            return DownloadResult::success();
        }

        $reason = $this->shortenReason((string) $firstError);

        return DownloadResult::failure($reason, $this->failureClassifier->classify($reason));
    }

    /**
     * Rejects a Telegram download whose estimated size would exceed the upload
     * limit, so the requester is told to pick another quality before any transfer.
     */
    private function sizeRejection(
        ?DownloadTask $downloadTask,
        string $videoUrl,
        string $downloadFormat,
        bool $allowPlaylist,
    ): ?string {
        if (!$downloadTask instanceof DownloadTask || !$downloadTask->isTelegramOrigin()) {
            return null;
        }

        $limitBytes = $this->telegramMaxUploadMb * 1024 * 1024;

        foreach ($this->sizeEstimator->estimate($videoUrl, $downloadFormat, $allowPlaylist) as $sizeBytes) {
            if ($limitBytes < $sizeBytes) {
                return \sprintf(
                    'Estimated file size %d MB exceeds the Telegram upload limit of %d MB. Choose another quality.',
                    (int) \round((float) $sizeBytes / 1024.0 / 1024.0),
                    $this->telegramMaxUploadMb,
                );
            }
        }

        return null;
    }

    private function progressReporter(?DownloadTask $downloadTask, string $telegramUserId): ?ProgressReporterInterface
    {
        if (!$downloadTask instanceof DownloadTask || !$downloadTask->isTelegramOrigin()) {
            return null;
        }

        $chatId = $downloadTask->getTelegramChatId() ?? (int) $telegramUserId;

        if (0 === $chatId) {
            return null;
        }

        $label = $downloadTask->getTitle() ?? $downloadTask->getUrlOrFail();

        return $this->progressNotifier->start($chatId, $label);
    }

    private function shortenReason(string $reason): string
    {
        $reason = \trim($reason);
        $lines  = \preg_split('/\r\n|\r|\n/', $reason) ?: [];
        $reason = \trim(\preg_replace('/^ERROR:\s*/i', '', \trim((string) ($lines[0] ?? ''))) ?? '');

        return \mb_substr('' === $reason ? 'Download failed.' : $reason, 0, 500);
    }
}
