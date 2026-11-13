<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\DownloadFailureKind;
use App\Enum\DownloadOutcome;

/**
 * The outcome of one download attempt together with the reason and the kind of
 * failure, so the handler can decide whether to retry and what to tell the user.
 */
final readonly class DownloadResult
{
    private function __construct(
        public DownloadOutcome $outcome,
        public ?string $errorMessage = null,
        public DownloadFailureKind $failureKind = DownloadFailureKind::NONE,
        public bool $sizeExceeded = false,
    ) {
    }

    public static function success(): self
    {
        return new self(DownloadOutcome::SUCCESS);
    }

    public static function failure(string $errorMessage, DownloadFailureKind $failureKind): self
    {
        return new self(DownloadOutcome::ERROR, $errorMessage, $failureKind);
    }

    /**
     * Rejected before the transfer because the file would not fit the Telegram
     * upload limit: permanent, and reported to the requester with its own message.
     */
    public static function sizeExceeded(string $errorMessage): self
    {
        return new self(DownloadOutcome::ERROR, $errorMessage, DownloadFailureKind::PERMANENT, true);
    }

    public function isSuccess(): bool
    {
        return DownloadOutcome::SUCCESS === $this->outcome;
    }

    public function isTransient(): bool
    {
        return DownloadFailureKind::TRANSIENT === $this->failureKind;
    }

    public function isSizeExceeded(): bool
    {
        return $this->sizeExceeded;
    }
}
