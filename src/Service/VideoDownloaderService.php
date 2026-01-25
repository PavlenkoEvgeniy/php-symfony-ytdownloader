<?php

declare(strict_types=1);

namespace App\Service;

use BotMan\BotMan\Exceptions\Base\BotManException;
use Psr\Log\LoggerInterface;

final readonly class VideoDownloaderService implements VideoProcessorInterface
{
    public const string BEST_VIDEO_DOWNLOAD_FORMAT     = 'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    public const string MODERATE_VIDEO_DOWNLOAD_FORMAT = 'bestvideo[height<=720][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    public const string POOR_VIDEO_DOWNLOAD_FORMAT     = 'bestvideo[height<=320][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    public const string NO_VIDEO_DOWNLOAD_FORMAT       = 'bestaudio/best';
    public const string OUTPUT_FILE_FORMAT_VIDEO       = '%(title)s-%(height)sp.%(ext)s';
    public const string OUTPUT_FILE_FORMAT_AUDIO       = '%(title)s.%(ext)s';
    public const string MERGE_OUTPUT_FORMAT_VIDEO      = 'mp4';
    public const string FORMAT_AUDIO                   = 'mp3';

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
