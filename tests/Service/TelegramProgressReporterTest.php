<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TelegramClientInterface;
use App\Service\TelegramProgressReporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TelegramProgressReporterTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new \DateTimeImmutable('2026-01-01 12:00:00'));
    }

    /**
     * Verifies a short download stays silent before the first interval.
     */
    public function testDoesNotSendBeforeTheFirstInterval(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->never())->method('sendMessage');
        $client->expects($this->never())->method('editMessageText');

        $reporter = $this->reporter($client, 300);

        $this->clock->sleep(299);
        $reporter->report(10);
    }

    /**
     * Verifies one message is created and later updates edit it.
     */
    public function testCreatesOneMessageAndEditsIt(): void
    {
        $edits = [];

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'Downloading Test: 10%')
            ->willReturn(100);
        $client->expects($this->exactly(2))
            ->method('editMessageText')
            ->willReturnCallback(static function (int $chatId, int $messageId, string $text) use (&$edits): void {
                $edits[] = [$chatId, $messageId, $text];
            });

        $reporter = $this->reporter($client, 300);

        $this->clock->sleep(300);
        $reporter->report(10);

        $this->clock->sleep(300);
        $reporter->report(30);

        $reporter->report(100);

        $this->assertSame([
            [42, 100, 'Downloading Test: 30%'],
            [42, 100, 'Downloading Test: 100%'],
        ], $edits);
    }

    /**
     * Verifies a failed edit falls back to a new message.
     */
    public function testFailedEditFallsBackToNewMessage(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnOnConsecutiveCalls(100, 101);
        $client->expects($this->once())
            ->method('editMessageText')
            ->willThrowException(new \RuntimeException('message deleted'));

        $reporter = $this->reporter($client, 300);

        $this->clock->sleep(300);
        $reporter->report(10);

        $this->clock->sleep(300);
        $reporter->report(20);
    }

    private function reporter(TelegramClientInterface $client, int $intervalSeconds): TelegramProgressReporter
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Downloading Test:');

        return new TelegramProgressReporter(
            $client,
            $translator,
            $this->createStub(LoggerInterface::class),
            $this->clock,
            42,
            'Test',
            $intervalSeconds,
        );
    }
}
