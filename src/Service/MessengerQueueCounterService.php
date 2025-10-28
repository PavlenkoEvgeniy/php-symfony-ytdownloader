<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class MessengerQueueCounterService
{
    public function __construct(
        private TransportInterface $asyncTransport,
    ) {
    }

    public function getQueueCount(): int
    {
        if ($this->asyncTransport instanceof MessageCountAwareInterface) {
            return $this->asyncTransport->getMessageCount();
        }

        return 0;
    }
}
