# Spec Delta

## ADDED Requirements

### Requirement: A user cancels their active downloads

When a Telegram user sends /cancel, every Download Task of theirs that is
`queued` or `processing` SHALL be removed: queued tasks disappear without
starting, a running transfer SHALL be stopped and its staged files
removed, and no success or failure message SHALL be sent for a cancelled
task. The answer SHALL report how many downloads were cancelled, or that
there was nothing to cancel.

#### Scenario: Cancelling a queued download

- **WHEN** a Telegram user sends /cancel while one of their tasks is queued
- **THEN** the task is removed without being started
- **AND** the answer reports one cancelled download

#### Scenario: Cancelling a running download

- **WHEN** a Telegram user sends /cancel while one of their tasks is processing
- **THEN** the running transfer is stopped
- **AND** the task is removed

#### Scenario: No notification for a cancelled download

- **WHEN** a Telegram user cancels a task that was processing
- **THEN** the requester receives neither a success nor a failure message

#### Scenario: Nothing to cancel

- **WHEN** a Telegram user sends /cancel while none of their tasks are queued or processing
- **THEN** the answer states that there was nothing to cancel
