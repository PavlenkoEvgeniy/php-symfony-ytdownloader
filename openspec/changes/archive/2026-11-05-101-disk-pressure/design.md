# Design

## Context

Every surface (UI, REST API v1/v2, Telegram) creates tasks through
`DownloadDispatcher`, which persists a task before dispatching the queue message.
The downloads directory lives on the same 20 GB SSD as Docker, the database and
logs. Orphan files are already defined and pruned, but only manually; orphan
files and the build cache were the largest reclaimable items on production.

## Goals / Non-Goals

**Goals:**

- Stop accepting new tasks before the disk is exhausted.
- Make orphan cleanup automatic.
- Reclaim stale Docker build cache and log files.
- Give the operator a runbook for a nearly full disk.

**Non-Goals:**

- Slimming the php-fpm image (separate change).
- Cancelling or pausing already accepted tasks.
- Per-surface or per-user thresholds.

## Decisions

- Check free space in `DownloadDispatcher` through a `DiskSpacePolicy`, before
  persisting the task, so every current and future surface is covered. The
  Telegram surface is rejected too: a 2 GB file can fill the disk regardless of
  where the request came from.
- Measure `disk_free_space()` on `APP_DOWNLOADS_DIR`, not `/`, because downloads
  live on the bind-mounted project volume.
- Configure the threshold with `MIN_FREE_DISK_MB` (default 5120) through
  `#[Autowire]`; one global threshold is enough for a single-tenant service.
- Do not prune before rejecting: the hourly cleanup and the runbook handle space;
  deleting files during a rejected request hides the problem and may remove media
  the user still wants.
- Map the rejection at the edges: the UI redirects to the Library with a flash;
  the API uses a 507 `HttpException`, serialized by the existing Problem Details
  listener; the Telegram handler answers the callback and keeps the chat usable.
- Schedule `app:downloads:prune` hourly in supervisor with the same retry pattern
  as `library-prune`; keep the admin button and the command for manual runs.
- Add `docker builder prune --all -f` to `make docker-prune`; the existing
  `docker system prune` left 1.16 GB of old build cache. Purge stale `var/log`
  files on deploy.
- Show a warning in the shared disk card when the policy is in Disk Pressure.
- Record the threshold and refusal rule in ADR 0010.

## Risks / Trade-offs

- A download that starts just above the threshold can still consume the headroom.
  Five gigabytes leaves room for the largest Telegram upload and a normal
  playlist; the runbook tells the operator how to recover.
- The guard applies to everyone, including admins; the admin can free space and
  retry.
- An external monitor is still the only way to learn about a full disk when no one
  is using the service; that is out of scope for this change.
