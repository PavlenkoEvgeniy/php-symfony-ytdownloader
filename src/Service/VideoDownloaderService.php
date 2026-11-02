<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\DownloadOutcome;
use BotMan\BotMan\Exceptions\Base\BotManException;
use Psr\Log\LoggerInterface;

final readonly class VideoDownloaderService implements VideoProcessorInterface
{
    public function __construct(
        private YoutubeDlWrapper $youtubeDl,
        private FormatResolver $formatResolver,
        private SourceManager $sourceManager,
        private LogManager $logManager,
        private LoggerInterface $logger,
        private TelegramNotifier $telegramNotifier,
    ) {
    }

    /**
     * @throws BotManException
     */
    #[\Override]
    public function process(string $videoUrl, string $format, ?string $telegramUserId = null): DownloadOutcome
    {
        $this->logManager->create('commenced', 'Started downloading.');
        $this->logManager->flush();

        [$downloadFormat, $mergeAsVideo] = $this->formatResolver->resolve($format);

        $collection = $this->youtubeDl->download($videoUrl, $downloadFormat, $mergeAsVideo);

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
            $size     = (float) $video->getFile()->getSize();

            if (null === $this->sourceManager->findByFilename($filename)) {
                $this->sourceManager->createFromDownloadedFile($filename, $path, $size);

                $this->logManager->create('success', \sprintf('File "%s" downloaded successfully.', $filename), $size);
                $this->logger->info(\sprintf('File "%s" downloaded successfully.', $filename));
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

        if ($telegramUserId) {
            if ($hasFiles) {
                $this->telegramNotifier->notifyFinished($telegramUserId, 'Downloading finished. You can find your file in the downloads directory. To download please visit the website.');
            } else {
                $this->telegramNotifier->notifyError($telegramUserId, 'Error during downloading: please try again with another link.');
            }
        }

        return $hasFiles ? DownloadOutcome::SUCCESS : DownloadOutcome::ERROR;
    }
}
