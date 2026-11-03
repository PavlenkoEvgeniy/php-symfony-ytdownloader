<?php

declare(strict_types=1);

namespace App\Exception;

final class TelegramApiException extends \RuntimeException
{
    public static function because(string $method, int $statusCode, string $description): self
    {
        return new self(\sprintf('Telegram API method "%s" failed with HTTP %d: %s', $method, $statusCode, $description));
    }
}
