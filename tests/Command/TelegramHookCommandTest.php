<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramHookCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TelegramHookCommandTest extends TestCase
{
    public function testExecuteSetsWebhookAndReturnsSuccess(): void
    {
        $token = 'test-token';
        $hostUrl = 'https://example.test';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('{"ok":true}');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.telegram.org/bot' . $token . '/setWebhook',
                [
                    'body' => [
                        'url' => $hostUrl . '/telegram/hook',
                    ],
                ]
            )
            ->willReturn($response);

        $command = new TelegramHookCommand($token, $hostUrl, $httpClient);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Reply from telegram', $tester->getDisplay());
    }
}
