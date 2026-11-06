# A processing lease marks a live download attempt

The task status cannot distinguish a worker that is still downloading from one
that died: both leave the row in `processing`. Reset used that status alone, so
resetting a live task started a second download while the first kept writing, and
the disk kept shrinking after the task was reported as an error (GitHub #120).

Every download attempt now holds an exclusive, non-blocking `flock` on
`%kernel.cache_dir%/task-locks/task-<id>.lock` for its whole duration. The worker
takes the lease before claiming the task in the database; Reset tries the same
lease and only resets a task when the lock is free. The kernel releases a lock
when the process dies, so a crashed worker cannot leave a phantom lease, and a
container restart drops all leases, which correctly makes every leftover
`processing` row stuck.

`claimProcessing` accepts both `queued` and `processing`, because the lease, not
the status, guarantees one live attempt. This also lets a redelivered message
re-run a task whose previous attempt died instead of being silently skipped.
`success` and `error` are still terminal for message delivery.

## Considered Options

- **A heartbeat column updated during the download** — a blocking yt-dlp process
  cannot update it without a streaming callback or a second process, and a stale
  heartbeat has the same ambiguity as a stale status.
- **Tracking worker pids and checking `/proc`** — fragile across container and
  process restarts, and harder to test.
- **Killing a live download from Reset** — turns Reset into a cancel operation and
  still needs to find the process; it is a separate feature.

## Consequences

- Locks live under `var/cache`; `cache:clear` deletes the files. Running workers
  keep their open descriptors, and the stack restarts workers around cache
  operations, so no live attempt loses its lease in practice.
- A worker killed with `SIGKILL` releases the lock while its yt-dlp child may
  still be finishing; the long timeout and supervisor's process-group kill make
  this rare, and orphan files stay covered by the hourly pruner.
- Reset reports skipped live tasks in the flash and the audit event.
