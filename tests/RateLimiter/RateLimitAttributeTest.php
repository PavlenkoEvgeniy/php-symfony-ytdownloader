<?php

declare(strict_types=1);

namespace App\Tests\RateLimiter;

use App\RateLimiter\RateLimitAttribute;
use PHPUnit\Framework\TestCase;

final class RateLimitAttributeTest extends TestCase
{
    /**
     * Verifies the attribute uses the default limit and interval.
     */
    public function testAttributeCanBeCreatedWithDefaults(): void
    {
        $attribute = new RateLimitAttribute();

        $this->assertSame(60, $attribute->getLimit());
        $this->assertSame(60, $attribute->getInterval());
    }

    /**
     * Verifies the attribute keeps custom limit and interval values.
     */
    public function testAttributeCanBeCreatedWithCustomValues(): void
    {
        $attribute = new RateLimitAttribute(limit: 120, interval: 120);

        $this->assertSame(120, $attribute->getLimit());
        $this->assertSame(120, $attribute->getInterval());
    }

    /**
     * Verifies a custom limit keeps the default interval.
     */
    public function testAttributePreservesCustomLimitOnly(): void
    {
        $attribute = new RateLimitAttribute(limit: 100);

        $this->assertSame(100, $attribute->getLimit());
        $this->assertSame(60, $attribute->getInterval());
    }

    /**
     * Verifies a custom interval keeps the default limit.
     */
    public function testAttributePreservesCustomIntervalOnly(): void
    {
        $attribute = new RateLimitAttribute(interval: 300);

        $this->assertSame(60, $attribute->getLimit());
        $this->assertSame(300, $attribute->getInterval());
    }
}
