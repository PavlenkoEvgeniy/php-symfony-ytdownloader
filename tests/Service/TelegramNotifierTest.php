<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TelegramClientInterface;
use App\Service\TelegramNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TelegramNotifierTest extends TestCase
{
    /**
     * Verifies error notifications are sent to the given chat.
     */
    public function testNotifyErrorSendsMessage(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'error occurred');

        (new TelegramNotifier($client, $this->createStub(LoggerInterface::class)))->notifyError('42', 'error occurred');
    }

    /**
     * Verifies a failed notification does not escape to the caller.
     */
    public function testNotifyErrorSwallowsClientFailures(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->method('sendMessage')->willThrowException(new \RuntimeException('telegram is down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        (new TelegramNotifier($client, $logger))->notifyError('42', 'error occurred');
    }
}
