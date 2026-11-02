<?php

declare(strict_types=1);

namespace App\Tests\Controller\Telegram;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

final class TelegramControllerTest extends WebTestCase
{
    /**
     * Verifies the hook is rejected when the bot is disabled and no message is dispatched.
     */
    public function testDownloadReturnsForbiddenWhenDisabled(): void
    {
        static::ensureKernelShutdown();
        \putenv('TELEGRAM_BOT_ENABLED=0');
        $_ENV['TELEGRAM_BOT_ENABLED']    = '0';
        $_SERVER['TELEGRAM_BOT_ENABLED'] = '0';

        $client = static::createClient();

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');
        static::getContainer()->set(MessageBusInterface::class, $bus);

        $client->request(Request::METHOD_POST, '/telegram/hook', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        $this->assertSame('Disabled', $client->getResponse()->getContent());
    }

    /**
     * Verifies the enabled hook logs the request and returns OK.
     */
    public function testDownloadReturnsOkAndLogsWhenEnabled(): void
    {
        static::ensureKernelShutdown();
        \putenv('TELEGRAM_BOT_ENABLED=1');
        $_ENV['TELEGRAM_BOT_ENABLED']    = '1';
        $_SERVER['TELEGRAM_BOT_ENABLED'] = '1';

        $client = static::createClient();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Telegram hook received');
        static::getContainer()->set(LoggerInterface::class, $logger);

        $client->request(Request::METHOD_POST, '/telegram/hook', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->assertSame('Dispatched', $client->getResponse()->getContent());
    }
}
