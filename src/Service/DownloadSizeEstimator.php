<?php

declare(strict_types=1);

namespace App\Service;

use P3s\YtDlp\YtDlpClientInterface;

/**
 * Reads the expected file size from downloader metadata without transferring
 * data. Sizes come from the metadata and are approximate for adaptive streams,
 * so callers treat the result as best-effort rather than a hard guarantee.
 */
final readonly class DownloadSizeEstimator implements DownloadSizeEstimatorInterface
{
    public function __construct(
        private YtDlpClientInterface $ytDlpProbeClient,
    ) {
    }

    #[\Override]
    public function estimate(string $videoUrl, string $downloadFormat, bool $allowPlaylist): array
    {
        try {
            $items = $this->ytDlpProbeClient->extractInfo($videoUrl, [
                'format'               => $downloadFormat,
                'yes-playlist'         => $allowPlaylist,
                'no-playlist'          => !$allowPlaylist,
                'socket-timeout'       => 15,
                'cookies-from-browser' => 'chromium+gnomekeyring:/tmp/chromium_data',
            ]);
        } catch (\Throwable) {
            // A failed probe must not block downloads: the delivery check is the hard limit.
            return [];
        }

        $sizes = [];

        foreach ($items as $item) {
            $size = $this->entrySize($item);

            if (null !== $size) {
                $sizes[] = $size;
            }
        }

        return $sizes;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function entrySize(array $entry): ?int
    {
        $requested = $entry['requested_formats'] ?? null;

        if (\is_array($requested) && [] !== $requested) {
            $total = 0;

            foreach ($requested as $format) {
                if (!\is_array($format)) {
                    return null;
                }

                $size = $this->formatSize($format);

                if (null === $size) {
                    // One missing stream makes the merged size unknowable.
                    return null;
                }

                $total += $size;
            }

            return $total;
        }

        return $this->formatSize($entry);
    }

    /**
     * @param array<string, mixed> $format
     */
    private function formatSize(array $format): ?int
    {
        $size = $format['filesize'] ?? $format['filesize_approx'] ?? null;

        return \is_numeric($size) ? (int) $size : null;
    }
}
