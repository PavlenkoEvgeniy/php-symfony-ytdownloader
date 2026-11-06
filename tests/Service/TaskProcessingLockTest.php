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
