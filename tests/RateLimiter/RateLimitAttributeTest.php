<?php

declare(strict_types=1);

namespace App\Tests\RateLimiter;

use App\RateLimiter\RateLimitAttribute;
use PHPUnit\Framework\TestCase;

final class RateLimitAttributeTest extends TestCase
{
    public function testAttributeCanBeCreatedWithDefaults(): void
    {
        $attribute = new RateLimitAttribute();

        $this->assertSame(60, $attribute->getLimit());
        $this->assertSame(60, $attribute->getInterval());
    }

    public function testAttributeCanBeCreatedWithCustomValues(): void
    {
        $attribute = new RateLimitAttribute(limit: 120, interval: 120);

        $this->assertSame(120, $attribute->getLimit());
        $this->assertSame(120, $attribute->getInterval());
    }

    public function testAttributePreservesCustomLimitOnly(): void
    {
        $attribute = new RateLimitAttribute(limit: 100);

        $this->assertSame(100, $attribute->getLimit());
        $this->assertSame(60, $attribute->getInterval());
    }

    public function testAttributePreservesCustomIntervalOnly(): void
    {
        $attribute = new RateLimitAttribute(interval: 300);

        $this->assertSame(60, $attribute->getLimit());
        $this->assertSame(300, $attribute->getInterval());
    }
}
