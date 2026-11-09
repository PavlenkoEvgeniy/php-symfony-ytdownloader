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
     * Verifies a download failure carries a generic text, the library link and a retry button.
     */
    public function testNotifyDownloadFailureSendsGenericTextAndRetryButton(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(
                42,
                "Download failed. Please try again later.\nhttps://example.test/ui/source",
                $this->callback(static function (array $markup): bool {
                    return 'retry:7' === ($markup['inline_keyboard'][0][0]['callback_data'] ?? null);
                }),
            )
            ->willReturn(1);

        $this->notifier($client)->notifyDownloadFailure('42', 7);
    }

    private function notifier(TelegramClientInterface $client, ?LoggerInterface $logger = null): TelegramNotifier
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => match ($id) {
                'telegram.download_error' => 'Download failed. Please try again later.',
                'telegram.retry'          => 'Re-download',
                default                   => $id,
            },
        );

        return new TelegramNotifier(
            $client,
            $logger ?? $this->createStub(LoggerInterface::class),
            $translator,
            'https://example.test',
        );
    }
}
