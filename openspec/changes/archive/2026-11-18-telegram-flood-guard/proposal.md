# Proposal

## Why

Request (2026-11-18) after the delivery incident: repeated messages in a
loop are unacceptable and must be impossible, whatever future bug produces
them. The delivery fixes removed the known causes (synchronous webhook
processing, redelivery without deduplication), but nothing in the system
today bounds the bot's output: any new bug in a handler, retry loop or
notification path could still flood a chat.

There is no GitHub issue for this work; the affected capability is
`download-intake`.

## What Changes

- A hard per-chat output ceiling in the Telegram client: at most ten
  outbound messages (text, media files and message edits together) per
  chat per minute. Anything beyond the ceiling is dropped and logged for
  administrators; the requester sees nothing extra, and processing is
  untouched.
- The update-id deduplication window grows from 30 minutes to 24 hours, so
  a late redelivery cannot repeat an accepted update.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-intake`: an ADDED requirement that no message flood can reach
  the requester.

## Impact

- `TelegramClient` gains the cache and a logger; every outbound text send,
  media send and message edit passes the ceiling.
- Tests: existing client tests construct the guarded client; new tests
  cover a passing burst, dropped excess, and the logged drop.

Out of scope:

- Rate limits on inbound updates (the dedup and offset already bound
  processing).
- Notifications to the requester about dropped messages (a notice is
  itself a message; administrators get the log entry instead).
