<?php

declare(strict_types=1);

namespace App\Tests\Message;

use App\Message\DownloadMessage;
use PHPUnit\Framework\TestCase;

final class DownloadMessageTest extends TestCase
{
    /**
     * Verifies the message keeps the required url and quality and defaults optional fields.
     */
    public function testMessageKeepsRequiredFields(): void
    {
        $message = new DownloadMessage('https://youtube.com/watch?v=test', 'best');

        $this->assertSame('https://youtube.com/watch?v=test', $message->url);
        $this->assertSame('best', $message->quality);
        $this->assertSame('', $message->telegramUserId);
        $this->assertNull($message->taskId);
    }

    /**
     * Verifies the message keeps the optional telegram user id and task id.
     */
    public function testMessageKeepsOptionalFields(): void
    {
        $message = new DownloadMessage('https://youtube.com/watch?v=test', 'moderate', '12345', 7);

        $this->assertSame('12345', $message->telegramUserId);
        $this->assertSame(7, $message->taskId);
    }

    /**
     * Verifies the message accepts every supported quality value.
     */
    public function testMessageAcceptsEveryQuality(): void
    {
        $qualities = ['best', 'moderate', 'poor', 'audio'];

        foreach ($qualities as $quality) {
            $message = new DownloadMessage('https://youtube.com/watch?v=test', $quality);

            $this->assertSame($quality, $message->quality);
        }
    }
}
