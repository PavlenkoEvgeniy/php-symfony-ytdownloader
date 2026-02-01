<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\VideoDownloaderOption;
use App\Service\FormatResolver;
use PHPUnit\Framework\TestCase;

final class FormatResolverTest extends TestCase
{
    /**
     * @return array<int, array{string, array{string, bool}}>
     */
    public function formatProvider(): array
    {
        return [
            ['best', [VideoDownloaderOption::BEST_VIDEO_DOWNLOAD_FORMAT->value, true]],
            ['moderate', [VideoDownloaderOption::MODERATE_VIDEO_DOWNLOAD_FORMAT->value, true]],
            ['poor', [VideoDownloaderOption::POOR_VIDEO_DOWNLOAD_FORMAT->value, true]],
            ['audio', [VideoDownloaderOption::NO_VIDEO_DOWNLOAD_FORMAT->value, false]],
            ['unknown', [VideoDownloaderOption::BEST_VIDEO_DOWNLOAD_FORMAT->value, true]],
        ];
    }

    /**
     * @dataProvider formatProvider
     *
     * @param array{string, bool} $expected
     */
    public function testResolveReturnsExpectedFormat(string $input, array $expected): void
    {
        $resolver = new FormatResolver();

        $this->assertSame($expected, $resolver->resolve($input));
    }
}
