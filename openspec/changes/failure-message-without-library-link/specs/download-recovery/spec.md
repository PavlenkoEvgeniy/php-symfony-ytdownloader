# Spec Delta

## MODIFIED Requirements

### Requirement: The requester is told that a download failed

When a Telegram-origin task ends in `error` for any reason other than the size
rejection, the bot SHALL send a generic failure message that does not include
the recorded reason, together with a Re-download action, and SHALL NOT include
a link to the web library. A task rejected by the size guard SHALL instead
receive its own actionable message without a Re-download action. The recorded
reason SHALL remain available to administrators in every case.

#### Scenario: Final Telegram failure

- **WHEN** a Telegram-origin task ends in `error` for a reason other than the size rejection
- **THEN** the bot sends a generic failure message
- **AND** the message does not include the recorded reason
- **AND** the message offers a Re-download action
- **AND** the message does not include a link to the web library

#### Scenario: Pressing Re-download in Telegram

- **WHEN** the requester presses Re-download for one of their error tasks
- **THEN** the task returns to `queued` as a fresh attempt
- **AND** a new message is dispatched for it

#### Scenario: Size rejection does not use the generic message

- **WHEN** a Telegram-origin task ends in `error` because of the size guard
- **THEN** the bot sends the size-rejection message instead of the generic failure message
