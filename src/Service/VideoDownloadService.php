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

readonly class VideoDownloadService
{
    public const BEST_VIDEO_DOWNLOAD_FORMAT     = 'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    public const MODERATE_VIDEO_DOWNLOAD_FORMAT = 'bestvideo[height<=720][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    public const POOR_VIDEO_DOWNLOAD_FORMAT     = 'bestvideo[height<=320][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    public const DRAFT_VIDEO_DOWNLOAD_FORMAT    = 'bestvideo[height<=240][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    public const NO_VIDEO_DOWNLOAD_FORMAT       = 'bestaudio/best';
    public const OUTPUT_FILE_FORMAT             = '%(title)s.%(ext)s';
    public const MERGE_OUTPUT_FORMAT_VIDEO      = 'mp4';
    public const FORMAT_AUDIO                   = 'mp3';

    public function __construct(
        private string $downloadsDir,
        private EntityManagerInterface $entityManager,
        private SourceRepository $sourceRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function process(string $videoUrl, string $format): void
    {
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
            case 'draft':
                $downloadFormat = self::DRAFT_VIDEO_DOWNLOAD_FORMAT;
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
                    ->output(sprintf('%s quality --- %s', ucfirst($format), self::OUTPUT_FILE_FORMAT))
            );
        } else {
            $collection = $yt->download(
                Options::create()
                    ->downloadPath($this->downloadsDir)
                    ->url($videoUrl)
                    ->extractAudio(true)
                    ->audioFormat(self::FORMAT_AUDIO)
                    ->output(sprintf('Audio format --- %s', self::OUTPUT_FILE_FORMAT))
            );
        }

        foreach ($collection->getVideos() as $video) {
            if (null !== $video->getError()) {
                $this->logger->error('Error during downloading', ['error' => $video->getError()]);

                $log = new Log();
                $log
                    ->setType('error')
                    ->setMessage(sprintf('Error during downloading: %s', $video->getError()))
                ;

                $this->entityManager->persist($log);
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

                    $log = new Log();
                    $log
                        ->setType('success')
                        ->setMessage(sprintf('Video download complete - %s', $filename))
                        ->setSize((float) $size)
                    ;

                    $this->entityManager->persist($log);
                }
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Finished downloading videos', ['videos' => $collection]);
    }
}
