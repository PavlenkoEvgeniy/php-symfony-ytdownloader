# Design

## Context

The Active Tasks page (`/admin/active-tasks`) is a display-only view with
existing POST controls that follow one pattern: CSRF token check, audit event,
flash, redirect back (purge queue, reset stuck, prune orphans). Error tasks are
kept until a re-download succeeds (see the repository note on recent active
tasks), so the error segment grows without bound.

## Goals / Non-Goals

**Goals:**

- One action that empties the `error` segment of Active Tasks, following the
  existing control pattern exactly.

**Non-Goals:**

- Touching `queued`, `processing` or `success` rows.
- Cleaning the failed-transport messages: rows in the failed queue are never
  redelivered without an explicit re-run, and a delivered message for a
  deleted task is ignored by the handler anyway.
- Per-task deletion or bulk deletion of success history.

## Decisions

- **Bulk delete in the repository, no new service.** The action is a single
  `DELETE … WHERE status = error` statement; the purge/reset services exist
  because they coordinate several stores (transport table, task lock). A
  service would add a layer with no logic to host.
- **Database-level foreign keys do the integrity work.** `source.download_task_id`
  is `ON DELETE SET NULL`, so a cleared task never orphans a library file, and
  audit rows keep the task id as a plain integer with no constraint.
- **Button visibility follows the badge.** The form renders only while the
  error counter is above zero, like purge renders only with queued tasks.

## Risks / Trade-offs

- [Audit rows reference task ids that no longer exist] → acceptable: audit
  target ids are informational and already have no foreign key.
- [A Telegram Re-download pressed after clearing finds no task] → the callback
  already answers "This download cannot be retried" for missing or non-error
  tasks.

## Migration Plan

Standard release; no schema change, no data migration. Rollback = previous tag.

## Open Questions

None.
