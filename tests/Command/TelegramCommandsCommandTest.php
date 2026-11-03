<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramCommandsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TelegramCommandsCommandTest extends TestCase
{
    /**
     * Verifies nothing is sent when the bot is disabled.
     */
    public function testSkipsWhenBotIsDisabled(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $tester = new CommandTester(new Command(null, $this->command($httpClient, false)));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('disabled', $tester->getDisplay());
    }

    /**
     * Verifies the command list is sent to setMyCommands.
     */
    public function testRegistersCommands(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn('{"ok":true}');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.test/bottest-token/setMyCommands',
                [
                    'json' => [
                        'commands' => [
                            ['command' => 'start', 'description' => 'Start the bot'],
                            ['command' => 'login', 'description' => 'Link your account'],
                            ['command' => 'logout', 'description' => 'Unlink your account'],
                            ['command' => 'cancel', 'description' => 'Cancel queued downloads'],
                            ['command' => 'status', 'description' => 'List active downloads'],
                            ['command' => 'help', 'description' => 'Show the command list'],
                        ],
                    ],
                    'timeout' => 20,
                ],
            )
            ->willReturn($response);

        $tester = new CommandTester(new Command(null, $this->command($httpClient, true)));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('registered', $tester->getDisplay());
    }

    /**
     * Verifies a rejected command list makes the command fail.
     */
    public function testFailsWhenTelegramRejectsCommands(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn('{"ok":false,"description":"Bad Request"}');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $tester = new CommandTester(new Command(null, $this->command($httpClient, true)));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('rejected', $tester->getDisplay());
    }

    /**
     * Verifies a transport error makes the command fail.
     */
    public function testFailsOnTransportError(): void
    {
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(
            new class('Connection refused') extends \RuntimeException implements TransportExceptionInterface {},
        );

        $tester = new CommandTester(new Command(null, $this->command($httpClient, true)));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Connection refused', $tester->getDisplay());
    }

    private function command(HttpClientInterface $httpClient, bool $enabled): TelegramCommandsCommand
    {
        return new TelegramCommandsCommand($enabled, 'test-token', 'https://api.test', $httpClient);
    }
}
