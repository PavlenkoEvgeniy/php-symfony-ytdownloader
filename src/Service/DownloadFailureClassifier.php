<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\DownloadFailureKind;

/**
 * Separates failures that another attempt may cure (network, throttling) from
 * failures that need a different link or a human decision. Unknown failures are
 * permanent on purpose: a wrong link should not be retried forever.
 */
final readonly class DownloadFailureClassifier implements DownloadFailureClassifierInterface
{
    /** @var list<string> */
    private const array TRANSIENT_PATTERNS = [
        'timed out',
        'timeout',
        'temporary failure',
        'temporary failure in name resolution',
        'connection reset',
        'connection refused',
        'connection aborted',
        'network is unreachable',
        'incompleteread',
        'read timed out',
        'remote end closed connection',
        'unable to download webpage',
        'http error 429',
        'http error 500',
        'http error 502',
        'http error 503',
        'http error 504',
        'eof occurred',
        'no space left on device',
    ];

    /** @var list<string> */
    private const array PERMANENT_PATTERNS = [
        'unsupported url',
        'private video',
        'video unavailable',
        'this video is not available',
        'this video is private',
        'sign in to confirm',
        'requested format is not available',
        'no video formats found',
        'no such file or directory',
        'invalid url',
        'not a valid url',
    ];

    #[\Override]
    public function classify(string $reason): DownloadFailureKind
    {
        foreach (self::PERMANENT_PATTERNS as $pattern) {
            if (false !== \stripos($reason, $pattern)) {
                return DownloadFailureKind::PERMANENT;
            }
        }

        foreach (self::TRANSIENT_PATTERNS as $pattern) {
            if (false !== \stripos($reason, $pattern)) {
                return DownloadFailureKind::TRANSIENT;
            }
        }

        return DownloadFailureKind::PERMANENT;
    }
}
