<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Entity\Source;
use App\Enum\DownloadOutcome;
use App\Repository\DownloadTaskRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class VideoDownloaderService implements VideoProcessorInterface
{
    public function __construct(
        private YoutubeDlWrapperInterface $youtubeDlWrapper,
        private FormatResolverInterface $formatResolver,
        private SourceManagerInterface $sourceManager,
        private LogManagerInterface $logManager,
        private LoggerInterface $logger,
        private TelegramNotifierInterface $telegramNotifier,
        private TranslatorInterface $translator,
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
    ) {
    }

    #[\Override]
    public function process(
        string $videoUrl,
        string $format,
        ?string $telegramUserId = null,
        ?int $taskId = null,
    ): DownloadOutcome {
        $telegramUserId ??= '';
        $downloadTask    = null === $taskId ? null : $this->downloadTaskRepository->findById($taskId);
        $origin          = $downloadTask?->getOrigin();
        $allowPlaylist   = !($downloadTask?->isTelegramOrigin() ?? false);

        $this->logManager->create('commenced', 'Started downloading.');
        $this->logManager->flush();

        [$downloadFormat, $mergeAsVideo] = $this->formatResolver->resolve($format);

        $collection = $this->youtubeDlWrapper->download($videoUrl, $downloadFormat, $mergeAsVideo, $allowPlaylist);

        $saved  = 0;
        $errors = 0;

        foreach ($collection->getVideos() as $video) {
            if (null !== $video->getError()) {
                ++$errors;

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

        if (0 === $saved && 0 === $errors) {
            $this->logManager->create('error', 'Error during downloading: no files were downloaded.');
            $this->logManager->flush();
        }

        // Always flush: files already on disk must get their Source row even when
        // some playlist items failed, otherwise Prune would treat them as orphans.
        $this->sourceManager->flush();

        $hasFiles = $saved > 0;

        if ($hasFiles) {
            $this->logManager->create('finished', 'Finished downloading.');
            $this->logManager->flush();
        }

        // Successful Telegram downloads are delivered as files by TelegramDeliveryMessageHandler.
        if ('' !== $telegramUserId && !$hasFiles) {
            $this->telegramNotifier->notifyError(
                $telegramUserId,
                $this->translator->trans('telegram.download_error'),
            );
        }

        return $hasFiles ? DownloadOutcome::SUCCESS : DownloadOutcome::ERROR;
    }
}
