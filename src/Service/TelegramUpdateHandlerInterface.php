<?php

declare(strict_types=1);

namespace App\Service;

interface TelegramUpdateHandlerInterface
{
    /**
     * @param array<string, mixed> $update
     */
    public function handle(array $update): void;
}
