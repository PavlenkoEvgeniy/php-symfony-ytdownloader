# Proposal

## Why

The Active Tasks page keeps every failed Download Task in `error` until it is
re-downloaded successfully, so the list only grows and operational failures
drown in stale rows. There is no way to clear them. There is no GitHub issue
for this change.

## What Changes

- A new action on the admin Active Tasks page clears the error list: it
  deletes every Download Task in `error` status, asks for confirmation,
  requires a CSRF token, and reports the cleared count in the admin flash and
  the audit trail.
- Download Tasks in other statuses (`queued`, `processing`, `success`) are not
  touched by the action.

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `admin-panel`: new requirement for clearing error tasks from the Active
  Tasks page (confirmation, CSRF, scope to `error` status, flash and audit
  reporting).

## Impact

- Code: admin dashboard controller (new POST action), Download Task repository
  (bulk delete by status), audit action enum, Active Tasks template, en/ru
  translations, admin controller tests.
- A Telegram requester who presses Re-download for a cleared task gets the
  existing "cannot be retried" answer; audit history keeps its rows (they
  reference the task id without a foreign key).
- Out of scope: deleting `success` history, per-task deletion, failed-transport
  message cleanup (failed messages are inert without an explicit re-run).
