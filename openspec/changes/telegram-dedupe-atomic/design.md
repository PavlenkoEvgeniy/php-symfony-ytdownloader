# Design

## Context

`TelegramUpdateMessageHandler` marked updates in the cache
(`getItem`/`isHit`/`save`). Two workers consuming the same update from the
webhook and the poller both read "not seen" before either saved, and both
produced side effects — observed as the doubled "Nothing to cancel."

## Goals / Non-Goals

**Goals:**
- Claiming an update is atomic across processes and delivery paths.
- No unbounded table growth.

**Non-Goals:**
- Removing the second delivery path (redundancy is wanted).
- Deduplicating anything else (messages, notifications).

## Decisions

- **Database primary key as the claim**: `INSERT INTO
  telegram_update_dedupe (update_id) … ON CONFLICT DO NOTHING` returns 0
  affected rows for a duplicate — a single round trip that is atomic by
  construction, unlike any check-then-set on a cache.
- **Postgres-specific `ON CONFLICT`** matches the project's storage
  (Doctrine on PostgreSQL); a portable alternative (catch unique
  violation) would turn normal duplicates into exceptions in the logs.
- **Seven-day retention pruned on the claim path**: message volume is a
  handful per day, the table stays in the hundreds of rows, and a delete
  with a timestamp filter costs nothing at that size.
- **The row records only what is needed** (`update_id`, `seen_at`): no
  entity mapping — the table is infrastructure, migrations are its only
  schema source, and Doctrine's schema validator ignores unmapped tables.

## Risks / Trade-offs

- A database outage now blocks update processing (previously the cache
  could absorb it). The cache was unreliable in exactly the case it
  mattered; accepting a hard dependency on the primary store is right.
- The prune runs only when an insert succeeds; an idle bot keeps nothing
  stale for long and the delete happens on the next message.
