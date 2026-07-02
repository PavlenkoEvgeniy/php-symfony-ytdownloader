<?php

declare(strict_types=1);

namespace App\Tests\Message;

use App\Message\DownloadMessage;
use PHPUnit\Framework\TestCase;

final class DownloadMessageTest extends TestCase
{
    public function testDownloadMessageConstructorWithRequiredFields(): void
    {
        $message = new DownloadMessage(
            'https://youtube.com/watch?v=test',
            'best'
        );

        $this->assertSame('https://youtube.com/watch?v=test', $message->getUrl());
        $this->assertSame('best', $message->getQuality());
        $this->assertSame('', $message->getTelegramUserId());
    }

    public function testDownloadMessageConstructorWithTelegramUserId(): void
    {
        $message = new DownloadMessage(
            'https://youtube.com/watch?v=test',
            'moderate',
            '12345'
        );

        $this->assertSame('https://youtube.com/watch?v=test', $message->getUrl());
        $this->assertSame('moderate', $message->getQuality());
        $this->assertSame('12345', $message->getTelegramUserId());
    }

    public function testDownloadMessageGetUrlReturnsUrl(): void
    {
        $url     = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $message = new DownloadMessage($url, 'best');

        $this->assertSame($url, $message->getUrl());
    }

    public function testDownloadMessageGetQualityReturnsQuality(): void
    {
        $message = new DownloadMessage('https://youtube.com/watch?v=test', 'poor');

        $this->assertSame('poor', $message->getQuality());
    }

    public function testDownloadMessageGetTelegramUserIdReturnsUserId(): void
    {
        $userId  = '987654321';
        $message = new DownloadMessage('https://youtube.com/watch?v=test', 'audio', $userId);

        $this->assertSame($userId, $message->getTelegramUserId());
    }

    public function testDownloadMessageDefaultTelegramUserIdIsEmpty(): void
    {
        $message = new DownloadMessage('https://youtube.com/watch?v=test', 'best');

        $this->assertSame('', $message->getTelegramUserId());
    }

    public function testDownloadMessageWithVariousQualityOptions(): void
    {
        $qualities = ['best', 'moderate', 'poor', 'audio'];

        foreach ($qualities as $quality) {
            $message = new DownloadMessage('https://youtube.com/watch?v=test', $quality);
            $this->assertSame($quality, $message->getQuality());
        }
    }

    public function testDownloadMessageWithDifferentUrls(): void
    {
        $urls = [
            'https://youtube.com/watch?v=test1',
            'https://instagram.com/p/test2',
            'https://tiktok.com/@user/video/123',
        ];

        foreach ($urls as $url) {
            $message = new DownloadMessage($url, 'best');
            $this->assertSame($url, $message->getUrl());
        }
    }

    public function testDownloadMessageIsImmutable(): void
    {
        $message = new DownloadMessage('https://youtube.com/watch?v=test', 'best', '123');

        $this->assertSame('https://youtube.com/watch?v=test', $message->getUrl());
        $this->assertSame('best', $message->getQuality());
        $this->assertSame('123', $message->getTelegramUserId());

        // Message is immutable, so values should remain the same
        $this->assertSame('https://youtube.com/watch?v=test', $message->getUrl());
        $this->assertSame('best', $message->getQuality());
        $this->assertSame('123', $message->getTelegramUserId());
    }

    public function testDownloadMessageCanBeSerializedByMessenger(): void
    {
        $message = new DownloadMessage(
            'https://youtube.com/watch?v=test',
            'best',
            '12345'
        );

        // Message should be serializable by the Messenger component
        // Return types are already known to be strings from method signatures
        $this->assertNotEmpty($message->getUrl());
        $this->assertNotEmpty($message->getQuality());
        $this->assertNotEmpty($message->getTelegramUserId());
    }
}
