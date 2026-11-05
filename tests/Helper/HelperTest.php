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
     * Verifies byte sizes are formatted with the expected unit and precision.
     *
     * @dataProvider bytesProvider
     */
    public function testFormatByBitesWorkingOk(int|float|null $bytes, int $precision, string $expected): void
    {
        $this->assertEquals(Helper::formatBytes($bytes, $precision), $expected);
    }

    /**
     * Verifies free-space figures are reported for the requested path.
     */
    public function testGetFreeSpaceUsesGivenPath(): void
    {
        $space = Helper::getFreeSpace(\sys_get_temp_dir());

        $this->assertArrayHasKey('free', $space);
        $this->assertArrayHasKey('used', $space);
        $this->assertArrayHasKey('total', $space);
        $this->assertGreaterThanOrEqual(0.0, $space['usedPercentage']);
        $this->assertLessThanOrEqual(100.0, $space['usedPercentage']);
    }
}
