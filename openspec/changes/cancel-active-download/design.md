# Design

## Context

`handleCancel` called `deleteQueuedByUser`, so a `processing` task was out
of reach: the row stayed, the single-slot check kept refusing new links,
and the /cancel answer was "Nothing to cancel." The wrapper already polls
every 200 ms for the stall watchdog, so there is a natural place to notice
a cancellation without touching yt-dlp internals.

## Goals / Non-Goals

**Goals:**
- /cancel frees the single slot whether the active task is queued or
  processing.
- A stopped transfer leaves nothing behind (the staging cleanup from the
  size-guard work already covers it) and notifies nobody.

**Non-Goals:**
- /logout semantics, web/admin cancellation, a cancelled-tasks history.

## Decisions

- **Cancellation = row removal, not a status.** The worker notices the
  deletion through a cheap `existsById` probe in its polling loop and
  stops the process. Removal keeps /cancel consistent with the queued
  case (tasks vanish, no error-list noise) and makes notification
  suppression free: the handler reloads the task after the attempt,
  finds nothing, `markError` no-ops on a missing row (its `find()` null
  check), and the notify branch requires a live task.
- **The probe bypasses the Doctrine identity map.** A plain `find()`
  would keep returning the cached entity long after the row was deleted,
  so the cancellation check is a scalar existence query instead of
  entity hydration.
- **The closure is best-effort**: an exception while probing aborts
  nothing — a database hiccup must not kill a healthy download; the next
  successful probe (200 ms later) still cancels.
- **Checked only inside the polling loop**, before the stall check: the
  metadata phase before the process is short, and the loop already owns
  process termination and staging cleanup.
- **`/logout` unchanged**: unlinking the account is not an abort command;
  it keeps cancelling queued tasks only.

## Risks / Trade-offs

- A cancellation landing in the final instants of a successful transfer
  can let the finished file reach the library while its task row is
  already gone: the Source insert then fails its foreign key, the message
  is retried and dies at the claim (task missing), and the file becomes a
  prunable orphan. The window is milliseconds and self-heals through the
  existing orphan tooling — accepted.
- The audit row for the final failed attempt keeps a `targetId` that no
  longer exists; audit metadata has no foreign key and is informational —
  accepted (recorded in the proposal's out-of-scope).
