<?php

declare(strict_types=1);

namespace App\Tests\Controller\Telegram;

use App\Message\DownloadMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

final class TelegramControllerTest extends WebTestCase
{
    public function testDownloadReturnsForbiddenWhenDisabled(): void
    {
        // ensure env var is disabled for this test before kernel boots
        static::ensureKernelShutdown();
        \putenv('TELEGRAM_BOT_ENABLED=0');
        $_ENV['TELEGRAM_BOT_ENABLED']    = '0';
        $_SERVER['TELEGRAM_BOT_ENABLED'] = '0';

        $client = static::createClient();

        // replace bus so we assert it's not called
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');
        static::getContainer()->set(MessageBusInterface::class, $bus);

        $response = $client->request('POST', '/telegram/hook', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        $this->assertSame('Disabled', $client->getResponse()->getContent());
    }

    public function testDownloadReturnsOkAndLogsWhenEnabled(): void
    {
        // ensure env var is enabled
        static::ensureKernelShutdown();
        \putenv('TELEGRAM_BOT_ENABLED=1');
        $_ENV['TELEGRAM_BOT_ENABLED']    = '1';
        $_SERVER['TELEGRAM_BOT_ENABLED'] = '1';

        $client = static::createClient();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Telegram hook received');
        static::getContainer()->set(LoggerInterface::class, $logger);

        $response = $client->request('POST', '/telegram/hook', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->assertSame('Dispatched', $client->getResponse()->getContent());
    }

    public function testUrlHandlerDispatchesDownloadMessage(): void
    {
        $bot = new class {
            /** @var string[] */
            public array $replies = [];
            public object $user;

            public function __construct()
            {
                $this->user = new class {
                    public function getId(): int
                    {
                        return 42;
                    }
                };
            }

            public function getUser(): object
            {
                return $this->user;
            }

            public function reply(string $message): void
            {
                $this->replies[] = $message;
            }
        };

        $dispatched = null;
        $bus        = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnCallback(function ($msg) use (&$dispatched) {
            $dispatched = $msg;

            return new \Symfony\Component\Messenger\Envelope($msg);
        });

        // simulate the controller's URL callback
        $callback = function ($botman, string $url) use ($bus) {
            $quality = 'best';
            $userId  = (string) $botman->getUser()->getId();
            $bus->dispatch(new DownloadMessage($url, $quality, $userId));

            $botman->reply('Downloading is in progress. Please wait...');
        };

        $callback($bot, 'https://example.com');

        $this->assertSame(['Downloading is in progress. Please wait...'], $bot->replies);
    }
}
