<?php

declare(strict_types=1);

namespace App\Exception;

final class DownloadIntakeDisabledException extends \DomainException
{
    public static function create(): self
    {
        return new self('New downloads through the web are disabled.');
    }
}
