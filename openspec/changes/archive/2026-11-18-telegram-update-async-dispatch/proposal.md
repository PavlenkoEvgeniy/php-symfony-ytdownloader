# Proposal

## Why

Field incident (2026-11-18): the bot flooded the requester with the same
"Checking link…" and quality keyboard every ~minute and stopped answering
/cancel. Diagnosis: the webhook handled an update **synchronously** —
probing the link and sending messages inside the HTTP request — so the
acknowledgement to Telegram came seconds later than its webhook timeout.
Telegram then redelivered the same update forever, each redelivery re-ran
the side effects, and the requester's later updates (/cancel) stayed in
Telegram's pending queue behind the unconfirmed one. There is no
deduplication by `update_id`, so a redelivery repeats everything.

There is no GitHub issue for this work; the affected capability is
`download-intake`.

## What Changes

- The webhook only validates and **queues** the update on the Messenger
  bus, then confirms to Telegram immediately — probing, replying and
  queuing downloads move to the background worker.
- A redelivered update is recognised by its `update_id` and processed at
  most once.
- A failure to queue returns 500, so Telegram retries instead of losing
  the update silently.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-intake`: an ADDED requirement that Telegram updates are
  acknowledged immediately, processed in the background, and that a
  redelivered update does not repeat its side effects.

## Impact

- `HookController` dispatches `TelegramUpdateMessage` instead of calling
  the update handler; the handler runs in a messenger worker.
- New message + handler; messenger routing gains the new message type.
- Tests: hook controller tests re-targeted at the bus; new handler tests
  for deduplication; existing Telegram handling tests unchanged (the
  handler contract is the same).

Out of scope:

- The webhook-level "Internal Server Error: can't upload the file"
  reported by Telegram during the incident: the error text belongs to a
  TDLib-hosted HTTP server, no request in our access logs ever returned a
  non-200, and with the immediate acknowledgement the redelivery cycle
  it fuelled cannot continue. Recorded as an open question in the journal
  in case it recurs.
- Retry policy for failed update processing beyond the transport's
  standard retries.
