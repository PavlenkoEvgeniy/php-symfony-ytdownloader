<?php

declare(strict_types=1);

namespace App\RateLimiter;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class RateLimitAttribute
{
    public function __construct(
        private readonly int $limit = 60,
        private readonly int $interval = 60,
    ) {
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }
}
