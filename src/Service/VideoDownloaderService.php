<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Source;
use App\Enum\DownloadOutcome;
use BotMan\BotMan\Exceptions\Base\BotManException;
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
    ) {
    }

    #[\Override]
    /**
     * @throws BotManException
     */
    public function process(string $videoUrl, string $format, ?string $telegramUserId = null): DownloadOutcome
    {
        $telegramUserId ??= '';

        $this->logManager->create('commenced', 'Started downloading.');
        $this->logManager->flush();

        [$downloadFormat, $mergeAsVideo] = $this->formatResolver->resolve($format);

        $collection = $this->youtubeDlWrapper->download($videoUrl, $downloadFormat, $mergeAsVideo);

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

        if ('' !== $telegramUserId) {
            if ($hasFiles) {
                $this->telegramNotifier->notifyFinished(
                    $telegramUserId,
                    $this->translator->trans('telegram.download_finished'),
                );
            } else {
                $this->telegramNotifier->notifyError(
                    $telegramUserId,
                    $this->translator->trans('telegram.download_error'),
                );
            }
        }

        return $hasFiles ? DownloadOutcome::SUCCESS : DownloadOutcome::ERROR;
    }
}
