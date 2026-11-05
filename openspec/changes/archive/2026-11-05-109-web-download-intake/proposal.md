# Proposal

## Why

Issue #109 asked for an admin-controlled way to stop new downloads from the web
while keeping the Telegram bot and the already downloaded Library available. The
capability is now shipped (v1.12.0) but its behavior is not captured anywhere
durable; this retroactive change records the contract before the next change
touches the intake.

## What Changes

- Introduce the `download-intake` capability: accepting new Download Tasks from
  the web UI, REST API v1/v2 and the Telegram bot.
- Describe the admin switch that disables the web surfaces while the Telegram
  surface keeps working and already accepted tasks are not revoked.
- Describe the rejection behavior: the UI leads to the Library, both APIs return
  403 Problem Details, and no Download Task is created.
- Record that the Library (listing and downloading Sources) is unaffected.

## Capabilities

### New Capabilities
- `download-intake`: accepting new Download Tasks from the web UI, REST API
  v1/v2 and the Telegram bot, including the admin switch for the web surfaces.

### Modified Capabilities
- None. This is a retroactive spec for a capability introduced in v1.12.0.

## Impact

- Behavior only: UI, REST API v1/v2, Telegram bot, admin panel.
- The code shipped in PR #110 (`v1.12.0`) and was patched in `v1.12.1`.
- Out of scope: Telegram delivery, download processing, Library retention, and
  the admin disk-usage card (belongs to `admin-panel`).
