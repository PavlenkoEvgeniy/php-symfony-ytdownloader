# Design

## Context

The stack runs a local Bot API server (aiogram) for API calls. During the
2026-11-18 cloud incident the server kept receiving updates — its
`getUpdates` queue held the requester's link — while cloud webhook delivery
stopped entirely, leaving the bot silent. The async-ack work already routes
every update through `TelegramUpdateMessage` with `update_id`
deduplication, so a second delivery path only needs to feed the same
pipeline.

## Goals / Non-Goals

**Goals:**
- The bot answers updates even when cloud webhook delivery is degraded.
- The two paths can run simultaneously without duplicate side effects.
- A restart never replays stale backlog.

**Non-Goals:**
- Recovering updates lost inside Telegram's infrastructure.
- Removing the webhook or the hook endpoint.

## Decisions

- **Polling the Bot API server is the transport for this deployment**: the
  server is the component that actually receives updates; polling it is the
  designed local-mode workflow. `--local` servers also lift the usual
  webhook timeout class entirely.
- **The command only fetches and dispatches**: each update goes onto the
  messenger bus as `TelegramUpdateMessage`, so acknowledgement semantics,
  deduplication and worker concurrency are exactly the ones the webhook
  path uses.
- **The offset is committed after dispatch** of the batch, persisted in the
  cache: a crash re-delivers at most one batch, which the update-id dedup
  already absorbs.
- **Cold start skips the backlog**: with no stored offset the command peeks
  the newest update (`offset=-1`) and starts after it. A long outage must
  not machine-gun hours-old links and cancels; the requester simply resends.
- **Long poll 20 s with a 30 s HTTP timeout**, 5 s backoff on request
  failures — no busy loop, and a dead server cannot spin the process.
- **One process** (`numprocs=1`): ordered delivery; the webhook path, when
  it recovers, may duplicate — dedup handles it.

## Risks / Trade-offs

- A webhook-recovered cloud and polling both deliver: second copy is
  dropped by `update_id` — harmless by design.
- An update accepted-but-not-yet-dispatched when the process dies is
  re-fetched (offset not advanced) and deduped if already handled.
- Polling occupies the server's `getUpdates` stream; tools that call
  `getUpdates` manually (like the incident peek) can steal updates —
  operational note, not a code concern.
