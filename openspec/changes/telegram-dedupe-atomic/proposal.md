# Proposal

## Why

Field report (2026-11-18), right after the poller deploy: one `/cancel`
produced **two** "Nothing to cancel." answers. With the cloud webhook
recovered, the same update now arrives twice (webhook and poller), and the
cache-based deduplication is a check-then-set — two workers can both see
"not seen" and both handle the update. The deduplication must be atomic;
a probabilistic guard is exactly the kind of thing that lets a doubled
reply through.

There is no GitHub issue for this work; the affected capability is
`download-intake`.

## What Changes

- Deduplication moves from the cache to a database table with a primary
  key on the update id: claiming an update is a single atomic
  `INSERT … ON CONFLICT DO NOTHING`, so concurrent deliveries and
  concurrent workers cannot both win.
- Rows older than seven days are pruned as part of the same path.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-intake`: no requirement text changes; the existing
  "Redelivered update is not repeated" scenario gains a deterministic
  implementation instead of a best-effort one.

## Impact

- New table via migration; the message handler drops the cache and claims
  through the database connection.
- Tests: the handler tests move from a cache double to a connection double
  covering the winning insert, the duplicate insert and the id-less
  payload.

Out of scope:

- Removing one of the delivery paths — both stay as redundancy and the
  atomic claim makes the overlap harmless.
