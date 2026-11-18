<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Carries one raw Telegram webhook update from the hook controller to the
 * background worker, so the webhook can acknowledge immediately.
 */
#[AsMessage]
final readonly class TelegramUpdateMessage
{
    /**
     * @param array<string, mixed> $update
     */
    public function __construct(
        public array $update,
    ) {
    }
}
