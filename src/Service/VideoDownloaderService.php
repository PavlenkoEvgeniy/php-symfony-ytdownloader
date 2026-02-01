<?php

declare(strict_types=1);

namespace App\Service;

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
    public function process(string $videoUrl, string $format, ?string $telegramUserId = null): void
    {
        $initialLog = $this->logManager->create('commenced', 'Started downloading.');
        $this->logManager->flush();

        [$downloadFormat, $mergeAsVideo] = $this->formatResolver->resolve($format);

        $collection = $this->youtubeDl->download($videoUrl, $downloadFormat, $mergeAsVideo);

        foreach ($collection->getVideos() as $video) {
            if (null !== $video->getError()) {
                $this->logger->error('Error during downloading', ['error' => $video->getError()]);

                $this->logManager->create('error', \sprintf('Error during downloading: %s', $video->getError()));
                $this->logManager->flush();

                if ($telegramUserId) {
                    $this->telegramNotifier->notifyError($telegramUserId, 'Error during downloading: please try again with another link.');

                    return;
                }

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
        }

        $this->logManager->create('finished', 'Finished downloading.');
        $this->logManager->flush();
        $this->sourceManager->flush();

        if ($telegramUserId) {
            $this->telegramNotifier->notifyFinished($telegramUserId, 'Downloading finished. You can find your file in the downloads directory. To download please visit the website.');
        }
    }
}
