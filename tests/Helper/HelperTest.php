<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Helper\Helper;
use PHPUnit\Framework\TestCase;

final class HelperTest extends TestCase
{
    /**
     * @return array<int, array{int|float|null, int, string}>
     */
    public function bytesProvider(): array
    {
        return [
            [null, 2, '0.0 B'],
            [-1, 2, '0.0 B'],
            [0, 2, '0.0 B'],
            [1000, 2, '1000.0 B'],
            [1234, 2, '1.2 KB'],
            [2345678, 2, '2.2 MB'],
            [12345678912, 2, '11.5 GB'],
            [123456789123456789, 2, '112283.3 TB'],
        ];
    }

    /**
     * @dataProvider bytesProvider
     */
    public function testFormatByBitesWorkingOk(int|float|null $bytes, int $precision, string $expected): void
    {
        $this->assertEquals(Helper::formatBytes($bytes, $precision), $expected);
    }
}
