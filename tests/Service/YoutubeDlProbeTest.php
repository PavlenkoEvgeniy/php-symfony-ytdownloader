<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\TelegramProbeException;
use App\Service\YoutubeDlProbe;
use P3s\YtDlp\YtDlpClientInterface;
use PHPUnit\Framework\TestCase;

final class YoutubeDlProbeTest extends TestCase
{
    /**
     * Verifies a single video is returned with its metadata.
     */
    public function testProbeReturnsSingleVideo(): void
    {
        $client = $this->createMock(YtDlpClientInterface::class);
        $client->expects($this->once())
            ->method('extractInfo')
            ->with('https://youtube.com/watch?v=test', $this->arrayHasKey('no-playlist'))
            ->willReturn([['_type' => 'video', 'title' => 'Test video', 'duration' => 125]]);

        $result = new YoutubeDlProbe($client)->probe('https://youtube.com/watch?v=test');

        $this->assertFalse($result->isPlaylist);
        $this->assertSame('Test video', $result->title);
        $this->assertSame(125, $result->duration);
    }

    /**
     * Verifies more than one entry is treated as a playlist.
     */
    public function testProbeDetectsPlaylist(): void
    {
        $client = $this->createStub(YtDlpClientInterface::class);
        $client->method('extractInfo')->willReturn([
            ['_type' => 'url', 'title' => 'First'],
            ['_type' => 'url', 'title' => 'Second'],
        ]);

        $result = new YoutubeDlProbe($client)->probe('https://youtube.com/playlist?list=test');

        $this->assertTrue($result->isPlaylist);
    }

    /**
     * Verifies probe failures are reported as a dedicated exception.
     */
    public function testProbeWrapsFailures(): void
    {
        $client = $this->createStub(YtDlpClientInterface::class);
        $client->method('extractInfo')->willThrowException(new \RuntimeException('boom'));

        $this->expectException(TelegramProbeException::class);

        new YoutubeDlProbe($client)->probe('https://youtube.com/watch?v=test');
    }

    /**
     * Verifies an empty metadata response is rejected.
     */
    public function testProbeRejectsEmptyMetadata(): void
    {
        $client = $this->createStub(YtDlpClientInterface::class);
        $client->method('extractInfo')->willReturn([]);

        $this->expectException(TelegramProbeException::class);

        new YoutubeDlProbe($client)->probe('https://youtube.com/watch?v=test');
    }
}
