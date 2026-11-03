<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\TelegramProbeResult;
use App\Exception\TelegramProbeException;
use P3s\YtDlp\YtDlpClientInterface;

final readonly class YoutubeDlProbe implements YoutubeDlProbeInterface
{
    public function __construct(
        private YtDlpClientInterface $ytDlpProbeClient,
    ) {
    }

    #[\Override]
    public function probe(string $url): TelegramProbeResult
    {
        try {
            $items = $this->ytDlpProbeClient->extractInfo($url, [
                'no-playlist'          => true,
                'flat-playlist'        => true,
                'playlist-end'         => 2,
                'socket-timeout'       => 15,
                'cookies-from-browser' => 'chromium+gnomekeyring:/tmp/chromium_data',
            ]);
        } catch (\Throwable $exception) {
            throw new TelegramProbeException('Could not read the link.', previous: $exception);
        }

        if ([] === $items) {
            throw new TelegramProbeException('The link produced no metadata.');
        }

        $first      = $items[0];
        $isPlaylist = \count($items) > 1 || 'playlist' === ($first['_type'] ?? null);

        return new TelegramProbeResult(
            isPlaylist: $isPlaylist,
            title: (string) ($first['title'] ?? $first['fulltitle'] ?? 'Video'),
            duration: isset($first['duration']) ? (int) $first['duration'] : null,
        );
    }
}
