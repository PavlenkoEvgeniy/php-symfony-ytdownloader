# Proposal

## Why

Second act of the 2026-11-18 incident: after the async-ack fix deployed,
Telegram's cloud bot endpoint became unreachable for this bot — `getMe` and
`getWebhookInfo` hang or answer 502 while a bogus token still answers 401,
and **no webhook deliveries arrive at all**. The updates themselves are
alive: the local Bot API server (already part of the stack for API calls)
holds the requester's newest link and 41 accumulated updates in its
`getUpdates` queue. The bot is silent purely because delivery relies on the
degraded cloud webhook.

There is no GitHub issue for this work; the affected capability is
`download-intake`.

## What Changes

- The bot API server is polled by a dedicated console command
  (`app:telegram-poll`, run by supervisord): fetched updates go through the
  same `TelegramUpdateMessage` pipeline with the same at-most-once
  processing, so the two delivery paths are interchangeable and safe
  together.
- The polling offset is persisted in the cache; a cold start begins after
  the newest known update so a long outage does not replay an old backlog.
- Polling becomes the primary transport of the deployment; the cloud
  webhook may keep delivering when it recovers — the update-id dedup makes
  the overlap harmless.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-intake`: the immediate-acknowledgement requirement now names
  both transports (webhook and Bot API polling) and adds scenarios for
  polling survival and backlog skipping.

## Impact

- New command, a new supervisord program, tests against `MockHttpClient` +
  `ArrayAdapter`.
- The webhook stays as configured; no data migration.

Out of scope:

- Removing the webhook registration or the hook endpoint.
- Recovering updates lost inside Telegram's outage.
