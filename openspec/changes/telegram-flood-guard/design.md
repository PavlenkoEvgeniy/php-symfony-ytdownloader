# Design

## Context

All outbound chat traffic funnels through `TelegramClient`
(`sendMessage`, `sendVideo`, `sendAudio`, `sendDocument`,
`editMessageText`). The incident showed a loop can produce thousands of
sends; the per-update dedup fixes the known cause but nothing bounds the
output itself.

## Goals / Non-Goals

**Goals:**
- A hard ceiling that holds regardless of the bug that produces messages.
- Zero extra messages when the ceiling trips (no "you are being rate
  limited" notices — those are messages too).
- No effect on download processing.

**Non-Goals:**
- Adaptive or per-user limits; one fixed ceiling.
- Guarding callback-query answers (toasts, not chat messages).

## Decisions

- **Fixed window per chat in the shared cache** (Redis-backed `cache.app`):
  a ten-send ceiling per 60 seconds, counting text, media and edits
  together — text and edits are the observed storm vector, media sends
  ride along so a file loop cannot flood either.
- **Drop and log, do not throw**: a thrown error would be retried by the
  messenger and could re-trigger other side effects; dropping keeps the
  pipeline running while the ceiling absorbs the bug. `sendMessage`
  returns `0` as the message id for a dropped send; callers already treat
  unknown ids as non-fatal.
- **Counter read-modify-write is not atomic**: two workers can overshoot
  the ceiling by their concurrency (a couple of messages), which still
  turns an unbounded storm into a bounded trickle. A lock would serialise
  all sends for a marginal strictness gain — rejected.
- **Window state expires after two windows** so idle chats leave nothing
  behind.
- **Deduplication window 30 min → 24 h**: Telegram may keep retrying an
  unconfirmed update long after the incident window; an update id is a few
  dozen bytes in the cache.

## Risks / Trade-offs

- Ten messages a minute is generous for humans (a link produces two) and
  tight enough that a loop saturates it instantly — the ceiling is the
  emergency brake, not a normal limiter.
- A legitimate extreme burst (e.g. ten separate links confirmed in one
  minute) would start dropping notifications; accepted, and the drops are
  visible in the log.
- Progress edits share the ceiling: with the minimum 1-minute progress
  interval the worst case stays under it.
