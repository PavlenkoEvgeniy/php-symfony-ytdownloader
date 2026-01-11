<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramHookCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TelegramHookCommandTest extends KernelTestCase
{
    public function testExecuteSendsWebhook(): void
    {
        $kernel = static::bootKernel();

        $response = new MockResponse('{"ok":true,"result":true}');
        $mock     = new MockHttpClient($response);

        $command = new TelegramHookCommand('TEST_BOT_TOKEN', 'https://example.local', $mock);
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Reply from telegram: {"ok":true,"result":true}', $output);
    }
}
