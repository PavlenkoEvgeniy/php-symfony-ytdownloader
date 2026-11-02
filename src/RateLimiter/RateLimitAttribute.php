<?php

declare(strict_types=1);

namespace App\RateLimiter;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class RateLimitAttribute
{
    public function __construct(
        private int $limit = 60,
        private int $interval = 60,
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
