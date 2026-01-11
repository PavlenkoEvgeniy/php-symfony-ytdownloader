<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\DownloadMessage;
use App\MessageHandler\DownloadMessageHandler;
use App\Service\VideoProcessorInterface;
use PHPUnit\Framework\TestCase;

final class DownloadMessageHandlerTest extends TestCase
{
    public function testInvokeCallsProcessWithTelegramId(): void
    {
        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->once())
            ->method('process')
            ->with('https://example.com', 'best', '12345');

        $handler = new DownloadMessageHandler($videoProcessor);

        $handler->__invoke(new DownloadMessage('https://example.com', 'best', '12345'));
    }

    public function testInvokeCallsProcessWithEmptyTelegramId(): void
    {
        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->once())
            ->method('process')
            ->with('https://example.com/2', 'audio', '');

        $handler = new DownloadMessageHandler($videoProcessor);

        $handler->__invoke(new DownloadMessage('https://example.com/2', 'audio'));
    }
}
