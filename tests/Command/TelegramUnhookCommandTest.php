<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramUnhookCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TelegramUnhookCommandTest extends TestCase
{
    /**
     * Verifies the unhook command clears the webhook URL and reports success.
     */
    public function testExecuteRemovesWebhookAndReturnsSuccess(): void
    {
        $token = 'test-token';

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn('{"ok":true}');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                \sprintf('https://api.telegram.org/bot%s/setWebhook', $token),
                ['body' => ['url' => '']],
            )
            ->willReturn($response);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []): string => \sprintf('Reply from telegram: %s', $parameters['%reply%'] ?? ''));

        $command = new Command(null, new TelegramUnhookCommand($token, $httpClient, $translator));
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Reply from telegram', $tester->getDisplay());
    }
}
