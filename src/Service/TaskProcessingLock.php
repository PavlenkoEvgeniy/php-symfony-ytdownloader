<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Per-task file lease shared by the worker and the admin/console processes in
 * the same container. A held lock is the direct evidence that a download
 * attempt is live, unlike the task status, which only says "picked up".
 */
final readonly class TaskProcessingLock implements TaskProcessingLockInterface
{
    private const string DIRECTORY = 'task-locks';

    public function __construct(
        #[Autowire('%kernel.cache_dir%')]
        private string $cacheDir,
    ) {
    }

    #[\Override]
    public function tryAcquire(int $taskId): ?TaskProcessingLeaseInterface
    {
        $path   = $this->path($taskId);
        $handle = @\fopen($path, 'c');

        if (false === $handle) {
            throw new \RuntimeException(\sprintf('Cannot open the task lock file "%s".', $path));
        }

        if (!\flock($handle, LOCK_EX | LOCK_NB)) {
            \fclose($handle);

            return null;
        }

        return new TaskProcessingLease($handle);
    }

    private function path(int $taskId): string
    {
        $directory = \rtrim($this->cacheDir, '/') . '/' . self::DIRECTORY;

        if (!\is_dir($directory) && !@\mkdir($directory, 0775, true) && !\is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Cannot create the task lock directory "%s".', $directory));
        }

        return \sprintf('%s/task-%d.lock', $directory, $taskId);
    }
}
