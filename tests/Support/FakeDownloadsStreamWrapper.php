<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Mimics the downloads directory to reach filesystem failures that root cannot produce.
 */
final class FakeDownloadsStreamWrapper
{
    public const string SCHEME = 'fakedownloads';

    /**
     * Builds a wrapper URL for the given host, e.g. "readable".
     */
    public static function url(string $host): string
    {
        return \sprintf('%s://%s', self::SCHEME, $host);
    }

    public static function register(): void
    {
        if (!\in_array(self::SCHEME, \stream_get_wrappers(), true)) {
            \stream_wrapper_register(self::SCHEME, self::class);
        }
    }

    public static function unregister(): void
    {
        if (\in_array(self::SCHEME, \stream_get_wrappers(), true)) {
            \stream_wrapper_unregister(self::SCHEME);
        }
    }

    /**
     * @var string[]
     */
    private array $entries = [];

    private int $position = 0;

    public function dir_opendir(string $path, int $options): bool
    {
        if (\str_contains($path, 'unreadable')) {
            return false;
        }

        $this->entries  = ['orphan.part'];
        $this->position = 0;

        return true;
    }

    public function dir_readdir(): string|false
    {
        return $this->entries[$this->position++] ?? false;
    }

    public function dir_rewinddir(): bool
    {
        $this->position = 0;

        return true;
    }

    public function dir_closedir(): bool
    {
        return true;
    }

    public function unlink(string $path): bool
    {
        throw new \RuntimeException('The file cannot be removed.');
    }

    public function rename(string $from, string $to): bool
    {
        throw new \RuntimeException('The file cannot be renamed.');
    }

    /**
     * @return array<string, int>
     */
    public function url_stat(string $path, int $flags): array
    {
        $timestamp = \time() - 7200;
        $isFile    = \str_ends_with(\rtrim($path, '/'), '/orphan.part');

        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => $isFile ? 0100644 : 0040755,
            'nlink'   => 1,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => $isFile ? 7 : 0,
            'atime'   => $timestamp,
            'mtime'   => $timestamp,
            'ctime'   => $timestamp,
            'blksize' => 4096,
            'blocks'  => 1,
        ];
    }
}
