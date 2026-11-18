<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramCommandsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
     * Verifies the command list is registered as the default set and once
     * per supported language with translated descriptions.
     */
    public function testRegistersCommandsForEveryLanguage(): void
    {
        $payloads = [];

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn('{"ok":true}');

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturnCallback(
            static function (string $method, string $url, array $options) use (&$payloads, $response): ResponseInterface {
                $payloads[] = $options['json'];

                return $response;
            },
        );

        $tester = new CommandTester(new Command(null, $this->command($httpClient, true)));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('registered', $tester->getDisplay());

        // Default English set, then one per supported language.
        $this->assertCount(10, $payloads);
        $this->assertArrayNotHasKey('language_code', $payloads[0]);
        $this->assertSame(
            ['command' => 'start', 'description' => 'Start the bot'],
            $payloads[0]['commands'][0],
        );
        $this->assertContains('language', \array_column($payloads[0]['commands'], 'command'));
        $this->assertContains('info', \array_column($payloads[0]['commands'], 'command'));

        $byLocale = [];
        foreach ($payloads as $payload) {
            if (isset($payload['language_code'])) {
                $byLocale[$payload['language_code']] = $payload;
            }
        }

        $this->assertArrayHasKey('ru', $byLocale);
        $this->assertSame(
            [
                'command'     => 'start',
                'description' => $this->translator()->trans('telegram.menu.start', [], 'messages', 'ru'),
            ],
            $byLocale['ru']['commands'][0],
        );
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
        return new TelegramCommandsCommand($enabled, 'test-token', 'https://api.test', $httpClient, $this->translator());
    }

    /**
     * Real translations from the shipped locale files.
     */
    private function translator(): TranslatorInterface
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());

        foreach (['en', 'ru', 'es', 'pt', 'de', 'fr', 'tr', 'ar', 'hi', 'zh'] as $locale) {
            $file = \dirname(__DIR__, 2) . '/translations/messages.' . $locale . '.yaml';

            if (\is_file($file)) {
                $translator->addResource('array', Yaml::parseFile($file), $locale, 'messages');
            }
        }

        return $translator;
    }
}
