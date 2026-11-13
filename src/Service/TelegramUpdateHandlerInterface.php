<?php

declare(strict_types=1);

namespace App\Service;

interface TelegramUpdateHandlerInterface
{
    /**
     * @param array<string, mixed> $update
     */
    public function handle(array $update): void;

    /**
     * Offers the quality selection for a link after its download was rejected
     * by the size guard, leaving out qualities known to exceed the limit and
     * the rejected quality itself.
     */
    public function offerQualitySelection(
        int $chatId,
        int $telegramUserId,
        string $url,
        string $title,
        string $rejectedQuality,
    ): void;
}
