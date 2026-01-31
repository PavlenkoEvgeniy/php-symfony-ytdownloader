<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramUnhookCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TelegramUnhookCommandTest extends TestCase
{
    public function testExecuteRemovesWebhookAndReturnsSuccess(): void
    {
        $token = 'test-token';

        $requester = function (string $url, $context) use ($token): string {
            TestCase::assertSame('https://api.telegram.org/bot' . $token . '/setWebhook', $url);
            TestCase::assertIsResource($context);

            return '{"ok":true}';
        };

        $command = new TelegramUnhookCommand($token, $requester);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Reply from telegram', $tester->getDisplay());
    }
}
