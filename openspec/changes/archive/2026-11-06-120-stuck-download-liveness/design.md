# Design

## Context

Messenger consumes `DownloadMessage` from the Doctrine transport and a
supervisor-managed worker runs the download (ADR-0001, ADR-0003). The task row is
persisted before the message, and `processing` only means "picked up". The
existing Reset assumes that every `processing` task is stuck, but a live worker
also leaves the row in `processing`; resetting it starts a second download while
the first is still writing. The yt-dlp client also has a fixed 300-second timeout,
which interrupts normal long downloads and makes the "stuck" state more likely.

## Goals / Non-Goals

**Goals:**

- Reset never starts a second attempt for a live download.
- A redelivered message can re-run a task whose previous attempt died.
- A legitimate long download is not killed after five minutes.
- The admin can see how many live tasks Reset skipped.

**Non-Goals:**

- Cancelling a running download from the admin page.
- Deciding what to do after a failure (change `121-download-failure-recovery`).
- Streaming progress to Telegram (change `122-telegram-download-progress`).

## Decisions

- **Per-task file lease (flock).** A worker acquires an exclusive, non-blocking
  lock file `%kernel.cache_dir%/task-locks/task-<id>.lock` before claiming the
  task and holds it for the whole attempt. The kernel releases the lock when the
  process dies, so a crashed worker cannot leave a phantom lease. The admin and
  console commands run in the same `ytdownloader-php-fpm` container as the
  workers, so they share the file system; a container restart drops every lock
  and every processing task is correctly considered stuck.
- **Acquire before claim.** `DownloadMessageHandler` takes the lease first and
  only then calls `claimProcessing`. A duplicate message cannot see a queued task
  and race the original attempt.
- **Claim accepts `queued` and `processing`.** The lease is what guarantees a
  single live attempt; the status update is the durable record. Allowing
  `processing` makes a redelivery after a dead attempt work. `success` and
  `error` stay untouched, so duplicates cannot revive a finished task.
- **Reset is per task.** Reset tries the lease for each processing task: a held
  lease means "live" and the task is skipped; a free lease means "stuck", the
  task goes back to `queued` and a fresh message is dispatched. In-flight
  transport messages are still made deliverable, as before.
- **Long process timeout.** `YoutubeDlWrapper` runs yt-dlp itself through
  Symfony Process, which is also the seam needed for progress reporting later.
  The timeout comes from `yt_dlp.timeout`, configured to 21600 seconds (6 hours)
  in `config/packages/yt_dlp.yaml`; the Telegram probe client keeps its explicit
  20-second timeout. On timeout the process is stopped and the attempt ends with
  an error reason.
- **Attempt termination.** The wrapper waits for the process to exit (or stops
  it) before returning, so the handler finalizes the task only after yt-dlp and
  its children are gone.
- **Rejected alternative: heartbeat column.** A blocking download cannot update
  a DB heartbeat without a streaming callback or a separate process, and a stale
  heartbeat has the same ambiguity as a stale `processing` row. A lock is a
  direct statement of liveness.

## Risks / Trade-offs

- Locks live under `var/cache`; `cache:clear` deletes the files. A running worker
  keeps its open descriptor, and the stack restarts workers around cache
  operations (`make restart`), so no live attempt loses its lease in practice.
- A worker that is stopped with `SIGKILL` releases the lock immediately, so Reset
  may start the task while an orphaned yt-dlp child is still finishing. The long
  timeout and supervisor's process-group kill make this rare; orphan files stay
  covered by the hourly pruner.
- Six hours is a bound, not a guarantee. A very large playlist can still reach
  it; the admin sees the error reason and can re-download it.
