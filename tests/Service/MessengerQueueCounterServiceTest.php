<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MessengerQueueCounterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class MessengerQueueCounterServiceTest extends TestCase
{
    public function testReturnsCountWhenTransportIsCountAware(): void
    {
        $transport = $this->createCountAwareTransport(7);

        $service = new MessengerQueueCounterService($transport);

        $this->assertSame(7, $service->getQueueCount());
    }

    public function testReturnsZeroWhenTransportIsNotCountAware(): void
    {
        $transport = $this->createMock(TransportInterface::class);

        $service = new MessengerQueueCounterService($transport);

        $this->assertSame(0, $service->getQueueCount());
    }

    private function createCountAwareTransport(int $count): TransportInterface
    {
        return new class($count) implements TransportInterface, MessageCountAwareInterface {
            public function __construct(private int $count)
            {
            }

            public function getMessageCount(): int
            {
                return $this->count;
            }

            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }

            public function send(Envelope $envelope): Envelope
            {
                return $envelope;
            }
        };
    }
}
