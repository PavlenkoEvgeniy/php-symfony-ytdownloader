<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TelegramClientInterface;
use App\Service\TelegramNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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

        $this->notifier($client)->notifyError('42', 'error occurred');
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

        $this->notifier($client, $logger)->notifyError('42', 'error occurred');
    }

    /**
     * Verifies a download failure carries the reason, the library link and a retry button.
     */
    public function testNotifyDownloadFailureSendsReasonAndRetryButton(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(
                42,
                $this->logicalAnd(
                    $this->stringContains('video not found'),
                    $this->stringContains('https://example.test/ui/source'),
                ),
                $this->callback(static function (array $markup): bool {
                    return 'retry:7' === ($markup['inline_keyboard'][0][0]['callback_data'] ?? null);
                }),
            )
            ->willReturn(1);

        $this->notifier($client)->notifyDownloadFailure('42', 'video not found', 7);
    }

    private function notifier(TelegramClientInterface $client, ?LoggerInterface $logger = null): TelegramNotifier
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = []): string => $id . ' ' . (string) ($parameters['%reason%'] ?? ''),
        );

        return new TelegramNotifier(
            $client,
            $logger ?? $this->createStub(LoggerInterface::class),
            $translator,
            'https://example.test',
        );
    }
}
