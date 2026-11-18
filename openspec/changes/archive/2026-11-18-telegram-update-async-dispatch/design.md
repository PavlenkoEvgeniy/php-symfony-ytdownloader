# Design

## Context

`HookController` called `TelegramUpdateHandler::handle()` inside the HTTP
request. The link path runs a metadata probe (seconds) before replying, so
the acknowledgement regularly exceeded Telegram's webhook window; each
timeout produced a redelivery that re-ran the whole handler (duplicate
"Checking link…" and keyboards), and the update was never confirmed, so
the requester's next updates could not be delivered. The stack already
uses Messenger with a supervisord-managed worker (ADR-0003) for downloads
and deliveries.

## Goals / Non-Goals

**Goals:**
- Webhook acknowledgement in milliseconds, independent of probe latency.
- At-most-once side effects per `update_id` even when Telegram redelivers.
- A failed acceptance is retried by Telegram instead of being dropped.

**Non-Goals:**
- Changing how updates are handled once they reach the worker.
- Explaining the TDLib-hosted 500 Telegram recorded during the incident.

## Decisions

- **Queue the update, confirm immediately**: the controller validates the
  secret and payload as before, dispatches `TelegramUpdateMessage` on the
  messenger bus and returns 200. The existing async transport keeps
  ordering semantics for update streams and survives worker restarts.
- **Deduplicate by `update_id` in the worker** (cache entry, 30 minutes):
  the check runs before any handler side effect, so a redelivered update
  sends nothing. The cache write happens before handling so an update that
  crashes mid-way is not retried into duplicate messages; a lost update is
  preferable to a message storm, matching the existing "acknowledge, do
  not redeliver after a partial side effect" stance.
- **500 on dispatch failure**: if the update cannot be queued, Telegram
  retrying is the correct recovery — this is the one case where redelivery
  is desired.
- **Worker concurrency keeps /cancel responsive** even while a link
  update is probing: two consumers share the queue and messenger does not
  serialise independent messages.

## Risks / Trade-offs

- A worker crash while processing an update loses it (cache marks it as
  seen before handling). Accepted: duplicate message storms are worse,
  and the user can re-send.
- The deduplication cache is best-effort under concurrent redelivery of
  the same update to two workers; the window is milliseconds and the
  original problem (eternal sequential redelivery) is fully covered.
- Update order between a link and a following /cancel may interleave
  across two workers; the single-slot rule already resolves that
  deterministically at intake.
