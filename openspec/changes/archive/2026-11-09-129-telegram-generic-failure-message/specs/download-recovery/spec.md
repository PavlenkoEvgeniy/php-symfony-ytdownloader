# Spec Delta

## MODIFIED Requirements

### Requirement: The requester is told that a download failed

When a Telegram-origin task ends in `error`, the bot SHALL send a generic
failure message that does not include the recorded reason, together with a
Re-download action and a link to the web library. The recorded reason SHALL
remain available to administrators.

#### Scenario: Final Telegram failure

- **WHEN** a Telegram-origin task ends in `error`
- **THEN** the bot sends a generic failure message
- **AND** the message does not include the recorded reason
- **AND** the message offers a Re-download action
- **AND** the message points to the web library

#### Scenario: Pressing Re-download in Telegram

- **WHEN** the requester presses Re-download for one of their error tasks
- **THEN** the task returns to `queued` as a fresh attempt
- **AND** a new message is dispatched for it

## RENAMED Requirements

- FROM: `### Requirement: The requester is told why a download failed`
- TO: `### Requirement: The requester is told that a download failed`
