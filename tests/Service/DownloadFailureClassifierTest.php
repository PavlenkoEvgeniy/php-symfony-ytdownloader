<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\DownloadFailureKind;
use App\Service\DownloadFailureClassifier;
use PHPUnit\Framework\TestCase;

final class DownloadFailureClassifierTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DownloadFailureKind}>
     */
    public static function reasons(): iterable
    {
        yield 'network timeout' => ['ERROR: Read timed out.', DownloadFailureKind::TRANSIENT];
        yield 'http 503' => ['Unable to download webpage: HTTP Error 503: Service Unavailable', DownloadFailureKind::TRANSIENT];
        yield 'http 429' => ['HTTP Error 429: Too Many Requests', DownloadFailureKind::TRANSIENT];
        yield 'private video' => ['ERROR: Private video. Sign in if you have access.', DownloadFailureKind::PERMANENT];
        yield 'unsupported url' => ['ERROR: Unsupported URL: https://example.test/video', DownloadFailureKind::PERMANENT];
        yield 'unknown reason is permanent' => ['Something unexpected happened', DownloadFailureKind::PERMANENT];
        yield 'stalled attempt' => ['Download stalled: no changes for 60 seconds.', DownloadFailureKind::PERMANENT];
    }

    /**
     * @dataProvider reasons
     */
    public function testClassifiesReasons(string $reason, DownloadFailureKind $expected): void
    {
        $this->assertSame($expected, (new DownloadFailureClassifier())->classify($reason));
    }

    public function testPermanentPatternWinsOverTransientSubstring(): void
    {
        $reason = 'Private video; the connection timed out while checking it.';

        $this->assertSame(DownloadFailureKind::PERMANENT, (new DownloadFailureClassifier())->classify($reason));
    }
}
