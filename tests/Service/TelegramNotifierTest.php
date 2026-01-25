<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TelegramBotService;
use App\Service\TelegramNotifier;
use BotMan\BotMan\BotMan;
use PHPUnit\Framework\TestCase;

final class TelegramNotifierTest extends TestCase
{
    public function testNotifyErrorSendsMessage(): void
    {
        $bot = $this->createMock(BotMan::class);
        $bot->expects($this->once())
            ->method('say')
            ->with('error occurred', 'user-id');

        /** @phpstan-ignore-next-line */
        $botService = $this->createMock(TelegramBotService::class);
        $botService->expects($this->once())
            ->method('getBot')
            ->willReturn($bot);

        $notifier = new TelegramNotifier($botService);
        $notifier->notifyError('user-id', 'error occurred');
    }

    public function testNotifyFinishedSendsMessage(): void
    {
        $bot = $this->createMock(BotMan::class);
        $bot->expects($this->once())
            ->method('say')
            ->with('done', 'user-id');

        /** @phpstan-ignore-next-line */
        $botService = $this->createMock(TelegramBotService::class);
        $botService->expects($this->once())
            ->method('getBot')
            ->willReturn($bot);

        $notifier = new TelegramNotifier($botService);
        $notifier->notifyFinished('user-id', 'done');
    }
}
