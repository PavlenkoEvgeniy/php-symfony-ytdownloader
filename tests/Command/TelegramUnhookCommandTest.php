<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramUnhookCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class TelegramUnhookCommandTest extends TestCase
{
    public function testExecuteShowsReplyFromTelegram(): void
    {
        $command = new TelegramUnhookCommand('dummy-token', function (string $url, $context) {
            // ensure token is present in URL
            TestCase::assertStringContainsString('dummy-token', $url);

            return '{"ok":true,"result":true}';
        });

        $tester = new CommandTester($command);
        $status = $tester->execute([]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Reply from telegram:', $output);
        $this->assertStringContainsString('{"ok":true,"result":true}', $output);
        $this->assertEquals(0, $status);
    }
}
