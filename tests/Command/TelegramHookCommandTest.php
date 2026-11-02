<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramHookCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TelegramHookCommandTest extends TestCase
{
    /**
     * Verifies the hook command registers the webhook URL and reports success.
     */
    public function testExecuteSetsWebhookAndReturnsSuccess(): void
    {
        $token   = 'test-token';
        $hostUrl = 'https://test.local';

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
                \sprintf('https://api.telegram.org/bot%s/setWebhook', $token),
                [
                    'body' => [
                        'url' => \sprintf('%s/telegram/hook', $hostUrl),
                    ],
                ]
            )
            ->willReturn($response);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []): string => \sprintf('Reply from telegram: %s', $parameters['%reply%'] ?? ''));

        $command = new Command(null, new TelegramHookCommand($token, $hostUrl, $httpClient, $translator));
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Reply from telegram', $tester->getDisplay());
    }
}
