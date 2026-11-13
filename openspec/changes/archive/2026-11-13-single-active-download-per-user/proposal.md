# Proposal

## Why

Request (2026-11-13): each user should be able to run only one download at
a time. When they send another link while the previous one is still going,
the bot should tell them to wait for it to finish or to cancel it with
/cancel. Today the bot accepts up to three concurrent downloads per user.

There is no GitHub issue for this work; the affected capabilities are
`download-intake` and `download-recovery`.

## What Changes

- The Telegram intake accepts a new link only when the user has no task in
  `queued` or `processing` state; otherwise it answers with a wait-or-cancel
  hint instead of probing the link.
- Pressing Re-download while another download of the same user is active is
  refused with the same hint (it would otherwise queue a second download).
- The concurrent-download limit drops from three to one.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-intake`: an ADDED requirement of one active Telegram download
  per user with the wait-or-cancel answer.
- `download-recovery`: a new scenario on the failure-notification
  requirement — Re-download is refused while another download is active.

## Impact

- `TelegramUpdateHandler`: active-task lookup replaces the counter, the
  hint becomes a single shared constant used by the link intake and the
  retry callback; the old three-in-progress message disappears.
- Tests: the limit test is updated, a retry-refusal test is added.

Out of scope:

- Web UI and REST API limits (the /cancel hint is Telegram-specific).
- Stopping a `processing` download: /cancel only removes queued tasks, so
  a running download must finish (bounded by the stall watchdog). Killing
  a transfer in flight needs cooperative cancellation and is a separate
  piece of work.
- Stale quality keyboards cannot bypass the limit: the pending selection
  is one per chat and its token dies as soon as it is consumed or replaced.
