# Spec Delta

## ADDED Requirements

### Requirement: A stalled attempt fails fast

When a download attempt makes no observable change — no downloader output
and no change to the files being written — for one minute from the start
of the attempt, the attempt SHALL be aborted. The task ends in `error`
with a stored reason, no automatic retry is scheduled, and a Telegram
requester is notified with the generic failure message. Attempts that keep
changing within the window SHALL NOT be aborted.

#### Scenario: Stalled attempt is aborted

- **WHEN** a download attempt makes no observable change for one minute from its start
- **THEN** the attempt is aborted
- **AND** the task ends in `error` with a stored reason
- **AND** no automatic retry is scheduled

#### Scenario: Stalled Telegram attempt notifies the requester

- **WHEN** a Telegram-origin attempt is aborted for stalling
- **THEN** the requester receives the generic failure message with a Re-download action

#### Scenario: Active attempt is not aborted

- **WHEN** an attempt changes something within the window
- **THEN** the attempt continues without interruption
