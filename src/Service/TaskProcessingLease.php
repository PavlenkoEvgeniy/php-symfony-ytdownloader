<?php

declare(strict_types=1);

namespace App\Service;

/**
 * The lease is released explicitly at the end of an attempt. The operating
 * system also releases the lock when the process dies, so a crashed worker
 * cannot leave a task permanently leased.
 */
final class TaskProcessingLease implements TaskProcessingLeaseInterface
{
    /**
     * @var resource|null
     */
    private $handle;

    /**
     * @param resource $handle
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    public function __destruct()
    {
        $this->release();
    }

    #[\Override]
    public function release(): void
    {
        $handle       = $this->handle;
        $this->handle = null;

        if (!\is_resource($handle)) {
            return;
        }

        \flock($handle, LOCK_UN);
        \fclose($handle);
    }
}
