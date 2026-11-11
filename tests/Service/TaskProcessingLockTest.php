<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TaskProcessingLock;
use PHPUnit\Framework\TestCase;

final class TaskProcessingLockTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir() . '/task-lock-test-' . \bin2hex(\random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
    }

    /**
     * Verifies only one live lease can exist for a task.
     */
    public function testSecondAcquireFailsWhileLeaseIsHeld(): void
    {
        $lock  = new TaskProcessingLock($this->cacheDir);
        $lease = $lock->tryAcquire(7);

        $this->assertNotNull($lease);
        $this->assertNull($lock->tryAcquire(7));
    }

    /**
     * Verifies releasing a lease makes the task claimable again.
     */
    public function testTaskIsClaimableAfterRelease(): void
    {
        $lock  = new TaskProcessingLock($this->cacheDir);
        $lease = $lock->tryAcquire(7);

        $this->assertNotNull($lease);
        $lease->release();

        $this->assertNotNull($lock->tryAcquire(7));
    }

    /**
     * Verifies different tasks have independent leases.
     */
    public function testDifferentTasksHaveIndependentLeases(): void
    {
        $lock = new TaskProcessingLock($this->cacheDir);

        $first = $lock->tryAcquire(1);

        $this->assertNotNull($first);
        $this->assertNotNull($lock->tryAcquire(2));
    }

    /**
     * Verifies a lock directory that cannot be created fails loudly instead of hanging.
     */
    public function testAcquireThrowsWhenTheLockDirectoryCannotBeCreated(): void
    {
        \mkdir($this->cacheDir, 0777, true);
        \file_put_contents(\sprintf('%s/task-locks', $this->cacheDir), 'not a directory');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create the task lock directory');

        new TaskProcessingLock($this->cacheDir)->tryAcquire(7);
    }

    /**
     * Verifies a lock file that cannot be opened fails loudly instead of hanging.
     */
    public function testAcquireThrowsWhenTheLockFileCannotBeOpened(): void
    {
        \mkdir(\sprintf('%s/task-locks/task-7.lock', $this->cacheDir), 0777, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot open the task lock file');

        new TaskProcessingLock($this->cacheDir)->tryAcquire(7);
    }

    private function removeDirectory(string $directory): void
    {
        if (!\is_dir($directory)) {
            return;
        }

        foreach (\glob($directory . '/*') ?: [] as $path) {
            if (\is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            @\unlink($path);
        }

        @\rmdir($directory);
    }
}
