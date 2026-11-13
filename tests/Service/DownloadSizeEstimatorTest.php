<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DownloadSizeEstimator;
use P3s\YtDlp\YtDlpClientInterface;
use PHPUnit\Framework\TestCase;

final class DownloadSizeEstimatorTest extends TestCase
{
    /**
     * Verifies split video and audio streams are summed into one estimate.
     */
    public function testEstimateSumsRequestedFormats(): void
    {
        $client = $this->createMock(YtDlpClientInterface::class);
        $client->expects($this->once())
            ->method('extractInfo')
            ->with('https://test.local', $this->arrayHasKey('format'))
            ->willReturn([[
                'requested_formats' => [
                    ['filesize' => 100_000_000],
                    ['filesize_approx' => 50_000_000],
                ],
            ]]);

        $sizes = new DownloadSizeEstimator($client)->estimate('https://test.local', 'bestvideo+bestaudio', false);

        $this->assertSame([150_000_000], $sizes);
    }

    /**
     * Verifies a single-format entry falls back to its approximate size.
     */
    public function testEstimateFallsBackToApproximateSize(): void
    {
        $client = $this->createStub(YtDlpClientInterface::class);
        $client->method('extractInfo')->willReturn([
            ['filesize' => null, 'filesize_approx' => 70_000_000],
        ]);

        $sizes = new DownloadSizeEstimator($client)->estimate('https://test.local', 'bestaudio', false);

        $this->assertSame([70_000_000], $sizes);
    }

    /**
     * Verifies an entry without any size produces no estimate.
     */
    public function testEstimateSkipsEntryWithoutKnownSize(): void
    {
        $client = $this->createStub(YtDlpClientInterface::class);
        $client->method('extractInfo')->willReturn([
            ['title' => 'No sizes here'],
        ]);

        $sizes = new DownloadSizeEstimator($client)->estimate('https://test.local', 'best', false);

        $this->assertSame([], $sizes);
    }

    /**
     * Verifies one unknown stream makes the merged size unknowable.
     */
    public function testEstimateSkipsEntryWhenStreamSizeIsMissing(): void
    {
        $client = $this->createStub(YtDlpClientInterface::class);
        $client->method('extractInfo')->willReturn([[
            'requested_formats' => [
                ['filesize' => 100_000_000],
                ['title' => 'audio without size'],
            ],
        ]]);

        $sizes = new DownloadSizeEstimator($client)->estimate('https://test.local', 'bestvideo+bestaudio', false);

        $this->assertSame([], $sizes);
    }

    /**
     * Verifies a playlist reports one estimate per entry.
     */
    public function testEstimateReturnsEachPlaylistEntry(): void
    {
        $client = $this->createStub(YtDlpClientInterface::class);
        $client->method('extractInfo')->willReturn([
            ['filesize' => 10_000_000],
            ['filesize' => 20_000_000],
        ]);

        $sizes = new DownloadSizeEstimator($client)->estimate('https://test.local/playlist', 'best', true);

        $this->assertSame([10_000_000, 20_000_000], $sizes);
    }

    /**
     * Verifies the resolved format selector and playlist flags reach the probe.
     */
    public function testEstimatePassesFormatAndPlaylistFlags(): void
    {
        $client = $this->createMock(YtDlpClientInterface::class);
        $client->expects($this->once())
            ->method('extractInfo')
            ->with(
                'https://test.local',
                $this->callback(static fn (array $options): bool => 'fmt' === ($options['format'] ?? null)
                    && true === ($options['no-playlist'] ?? null)
                    && false === ($options['yes-playlist'] ?? null)),
            )
            ->willReturn([]);

        new DownloadSizeEstimator($client)->estimate('https://test.local', 'fmt', false);
    }

    /**
     * Verifies a failed probe yields no estimate instead of an exception.
     */
    public function testEstimateReturnsEmptyListOnProbeFailure(): void
    {
        $client = $this->createStub(YtDlpClientInterface::class);
        $client->method('extractInfo')->willThrowException(new \RuntimeException('boom'));

        $sizes = new DownloadSizeEstimator($client)->estimate('https://test.local', 'best', false);

        $this->assertSame([], $sizes);
    }
}
