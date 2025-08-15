<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Log;
use App\Entity\Source;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;

final readonly class VideoDownloadService
{
    public const BEST_VIDEO_DOWNLOAD_FORMAT     = 'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]';
    public const MODERATE_VIDEO_DOWNLOAD_FORMAT = 'bestvideo[height<=720][ext=mp4]+bestaudio[ext=m4a]';
    public const POOR_VIDEO_DOWNLOAD_FORMAT     = 'bestvideo[height<=320][ext=mp4]+bestaudio[ext=m4a]';
    public const NO_VIDEO_DOWNLOAD_FORMAT       = 'bestaudio/best';
    public const OUTPUT_FILE_FORMAT_VIDEO       = '%(title)s-%(height)sp.%(ext)s';
    public const OUTPUT_FILE_FORMAT_AUDIO       = '%(title)s.%(ext)s';
    public const MERGE_OUTPUT_FORMAT_VIDEO      = 'mp4';
    public const FORMAT_AUDIO                   = 'mp3';

    public function __construct(
        private readonly string $downloadsDir,
        private readonly EntityManagerInterface $entityManager,
        private readonly SourceRepository $sourceRepository,
        private readonly LoggerInterface $logger,
        private readonly TelegramBotService $telegramBotService,
    ) {
    }

    public function process(string $videoUrl, string $format, ?string $telegramUserId = null): void
    {
        $log = new Log();
        $log
            ->setType('in progress')
            ->setMessage('Started downloading.')
        ;

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $yt = new YoutubeDl();

        $downloadFormat = '';
        $mergeAsVideo   = true;

        switch ($format) {
            case 'best':
                $downloadFormat = self::BEST_VIDEO_DOWNLOAD_FORMAT;
                break;
            case 'moderate':
                $downloadFormat = self::MODERATE_VIDEO_DOWNLOAD_FORMAT;
                break;
            case 'poor':
                $downloadFormat = self::POOR_VIDEO_DOWNLOAD_FORMAT;
                break;
            case 'audio':
                $downloadFormat = self::NO_VIDEO_DOWNLOAD_FORMAT;
                $mergeAsVideo   = false;
                break;
        }

        if ($mergeAsVideo) {
            $collection = $yt->download(
                Options::create()
                    ->downloadPath($this->downloadsDir)
                    ->url($videoUrl)
                    ->format($downloadFormat)
                    ->mergeOutputFormat(self::MERGE_OUTPUT_FORMAT_VIDEO)
                    ->output(self::OUTPUT_FILE_FORMAT_VIDEO)
            );
        } else {
            $collection = $yt->download(
                Options::create()
                    ->downloadPath($this->downloadsDir)
                    ->url($videoUrl)
                    ->extractAudio(true)
                    ->audioFormat(self::FORMAT_AUDIO)
                    ->output(self::OUTPUT_FILE_FORMAT_AUDIO)
            );
        }

        foreach ($collection->getVideos() as $video) {
            if (null !== $video->getError()) {
                $this->logger->error('Error during downloading', ['error' => $video->getError()]);

                $errorLog = new Log();
                $errorLog
                    ->setType('error')
                    ->setMessage(\sprintf('Error during downloading: %s', $video->getError()))
                ;

                $this->entityManager->persist($errorLog);

                if ($telegramUserId) {
                    $this->telegramBotService->getBot()->say(
                        \sprintf('Error during downloading: please try again with another link.'),
                        $telegramUserId,
                    );
                    exit;
                } else {
                    $this->logger->error(\sprintf('Error during downloading: %s', $video->getError()));
                }
            } else {
                $filename = $video->getFile()->getBasename();
                $path     = $video->getFile()->getPath();
                $size     = $video->getFile()->getSize();

                $source = $this->sourceRepository->findOneByFilename($filename);

                if (null === $source) {
                    $source = new Source();
                    $source
                        ->setFilename($filename)
                        ->setFilepath($path)
                        ->setSize((float) $size)
                    ;

                    $this->entityManager->persist($source);

                    $itemDownloadLog = new Log();
                    $itemDownloadLog
                        ->setType('success')
                        ->setMessage(\sprintf('File "%s" downloaded successfully.', $filename))
                        ->setSize((float) $size)
                    ;

                    $this->entityManager->persist($itemDownloadLog);

                    $this->logger->info(\sprintf('File "%s" downloaded successfully.', $filename));
                }
            }
        }

        $log
            ->setType('finished')
            ->setMessage('Finished downloading.');

        $this->entityManager->persist($log);

        $this->entityManager->flush();

        if ($telegramUserId) {
            $this->telegramBotService->getBot()->say(
                'Downloading finished. You can find your file in the downloads directory. To download please visit the website.',
                $telegramUserId,
            );
        }
    }
}
