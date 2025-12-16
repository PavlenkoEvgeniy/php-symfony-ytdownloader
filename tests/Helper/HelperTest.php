<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Helper\Helper;
use PHPUnit\Framework\TestCase;

final class HelperTest extends TestCase
{
    /**
     * @return float[]
     */
    public function bytesProvider(): array
    {
        return [
            [
                'bytes'     => null,
                'precision' => 2,
                'expected'  => '0.0 B',
            ],
            [
                'bytes'     => -1,
                'precision' => 2,
                'expected'  => '0.0 B',
            ],
            [
                'bytes'     => 0,
                'precision' => 2,
                'expected'  => '0.0 B',
            ],
            [
                'bytes'     => 1000,
                'precision' => 2,
                'expected'  => '1000.0 B',
            ],
            [
                'bytes'     => 1234,
                'precision' => 2,
                'expected'  => '1.2 KB',
            ],
            [
                'bytes'     => 2345678,
                'precision' => 2,
                'expected'  => '2.2 MB',
            ],
            [
                'bytes'     => 12345678912,
                'precision' => 2,
                'expected'  => '11.5 GB',
            ],
            [
                'bytes'     => 123456789123456789,
                'precision' => 2,
                'expected'  => '112283.3 TB',
            ],
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
