# Design

## Context

`handleUrl` accepted a link while `countActiveByUser() < 3`; the limit and
its "You already have 3 downloads in progress." answer were unspecced.
`handleRetry` had no active-task check at all, and the pending selection
token is one per chat, consumed when a quality is picked.

## Goals / Non-Goals

**Goals:**
- One `queued`/`processing` task per Telegram user, enforced on every path
  that would create one from the bot (new link, Re-download).
- A single, honest answer: wait for the current download or cancel it with
  /cancel.

**Non-Goals:**
- Web/API concurrency limits.
- Aborting a `processing` download (see proposal).

## Decisions

- **Lookup instead of counter**: `findActiveByUser($user, 1)` replaces
  `countActiveByUser()` — one query answers both "is anything active" and
  "which task", and the constant limit disappears in favour of a simple
  emptiness check. The limit of one is expressed by the query's result, not
  by a magic number.
- **One shared hint constant** for the intake answer and the retry toast,
  so the wording the requester sees is identical on both paths.
- **Order of checks on a new link** stays: duplicate-URL first (a more
  precise answer for the same link), then the active-slot check, then the
  probe — an refused link never costs a metadata probe.
- **Re-download is guarded after task validation**: a bad/expired callback
  keeps its own answer; only a valid, retriable error task falls through to
  the slot check.
- **No guard on the quality callback**: after a task is queued the pending
  token is consumed, and a refused link never stored one, so a stale
  keyboard cannot produce a second active task — the token comparison
  rejects it as expired.
- **The hint mentions /cancel for a queued download and a processing one
  alike** (the requester's requested wording). /cancel only removes queued
  tasks; a running download must finish within the stall watchdog's bounds.
  Making /cancel kill a transfer in flight needs cooperative cancellation —
  deliberately left out, recorded as an open question in the journal.

## Risks / Trade-offs

- A user whose download is running and long hears "or cancel it with
  /cancel" and finds /cancel reports nothing to cancel when the task is
  already `processing`. Accepted for now per the requested wording; the
  state-aware split is a small follow-up if it confuses people.
- Serializing downloads per user lowers throughput for power users; that is
  the point of the request.
