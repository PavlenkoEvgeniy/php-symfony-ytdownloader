# Proposal

## Why

Field report on `v1.17.0` (2026-11-13): the single-slot hint tells the user
to "Wait for it to finish, or cancel it with /cancel", but while the task
is already `processing` the /cancel command answers **"Nothing to cancel"**
— the promise in the hint is empty exactly when it matters, and with one
slot per user the person is locked in until the transfer ends.

There is no GitHub issue for this work; the affected capability is
`queue`.

## What Changes

- `/cancel` removes every active task of the user: `queued` tasks
  (as today) and a `processing` task too — its running transfer is
  stopped and the staging files go with it.
- A cancelled download sends no success and no failure message; the
  /cancel answer reports the number of cancelled downloads.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `queue`: an ADDED requirement describing /cancel over active
  (queued and processing) downloads — the command was unspecified until
  now.

## Impact

- `DownloadTaskRepository`: `deleteActiveByUser` replaces the
  queued-only delete on the cancel path; a lightweight `existsById`
  lookup feeds the cancellation check.
- `YoutubeDlWrapperInterface::download` gains an optional cancellation
  closure, polled in the existing 200 ms loop — the same loop the stall
  watchdog uses.
- `DownloadMessageHandler` needs no change: after cancellation the task
  row is gone, the reload finds nothing, `markError` is a no-op on a
  missing row and the notification branch requires a live task — the
  message is naturally suppressed.
- Tests: cancel/limit handler tests, wrapper cancellation test, five
  `download()` mock expectations gain the new argument, a
  no-notification-on-cancel test.

Out of scope:

- `/logout` still cancels queued tasks only (unlinking is not an abort
  button).
- Cancelling from the web UI or the admin panel.
- Audit entries with a `targetId` of a removed task: the audit row keeps
  its textual reason and has no foreign key, which is informational by
  design.
