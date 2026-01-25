<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FormatResolver;
use App\Service\VideoDownloaderService;
use PHPUnit\Framework\TestCase;

final class FormatResolverTest extends TestCase
{
    /**
     * @return array<int, array{string, array{string, bool}}>
     */
    public function formatProvider(): array
    {
        return [
            ['best', [VideoDownloaderService::BEST_VIDEO_DOWNLOAD_FORMAT, true]],
            ['moderate', [VideoDownloaderService::MODERATE_VIDEO_DOWNLOAD_FORMAT, true]],
            ['poor', [VideoDownloaderService::POOR_VIDEO_DOWNLOAD_FORMAT, true]],
            ['audio', [VideoDownloaderService::NO_VIDEO_DOWNLOAD_FORMAT, false]],
            ['unknown', [VideoDownloaderService::BEST_VIDEO_DOWNLOAD_FORMAT, true]],
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
