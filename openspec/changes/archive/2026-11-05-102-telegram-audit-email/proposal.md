# Proposal

## Why

Downloads made through the Telegram bot are recorded in the Audit Trail with the
numeric Telegram chat id as the actor. The administrator sees a number instead of
a person and cannot match the event to a User without looking up the Telegram
Link by hand. The linked User is already known when the event is written, so the
Audit Trail can identify the actor by the User's email.

GitHub issue: #102.

## What Changes

- Audit Events for actions performed through the Telegram bot name the linked
  User as the actor: actor type `user`, the User's id, and the User's email as the
  actor label. The channel stays Telegram.
- The Telegram chat id is kept in the event metadata for traceability.
- The rule covers every Telegram-origin event: download requested, download
  completed or failed, Telegram delivery outcome, and the Source deletion caused
  by a successful delivery.
- If no linked User is available, the event keeps the previous shape: actor type
  `telegram` with the chat id as the actor label.
- Audit Events recorded before the change are not rewritten.

## Capabilities

### New Capabilities

- `audit`: the Audit Trail — actor, channel, target and outcome of security- and
  operation-relevant actions. This change introduces the attribution of Telegram
  actions to the linked User.

## Impact

- The audit writer and its Telegram call sites: `AuditLogger` and its interface,
  `DownloadDispatcher`, `DownloadMessageHandler`,
  `TelegramDeliveryMessageHandler`.
- The Audit Event admin view needs no change: it already shows the actor label.
- Out of scope: backfilling historical events, an actor-type filter in the admin,
  auditing the Telegram `/logout`, and changing the delivery behavior itself.
