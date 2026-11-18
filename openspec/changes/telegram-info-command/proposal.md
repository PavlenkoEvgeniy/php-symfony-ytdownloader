# Proposal

## Why

Request (2026-11-18): the requester wants to see the running application
version and when the last update was deployed directly from the bot, and
`/info` should be discoverable in the command menu.

There is no GitHub issue for this work; the affected capability is
`download-intake` (the Telegram surface capability).

## What Changes

- New `/info` command: replies with the application version (the same
  `APP_VERSION` the web footer and health check use) and the time of the
  last deployment, in UTC.
- `/info` is registered in the Telegram command menu and listed in
  `/help`.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-intake`: an ADDED requirement that `/info` reports version and
  last update and appears in the menu.

## Impact

- `TelegramUpdateHandler` gains the command, the version and the project
  directory; the deployment time comes from the `.env` file's mtime (the
  checkout rewrites it at every release), with "unknown" as fallback.
- `TelegramCommandsCommand` menu and `/help` text gain the entry.
- Tests: handler reply test with a fixed mtime, menu test update.

Out of scope:

- Writing an explicit deploy-timestamp file in the deploy pipeline (the
  `.env` mtime already tracks releases); changing the web UI.
