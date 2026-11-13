# Spec Delta

## ADDED Requirements

### Requirement: Oversized Telegram downloads are rejected before transfer

For a Telegram-origin Download Task, the service SHALL estimate the final file
size of the selected quality from metadata before transferring any data. If the
estimate exceeds the configured Telegram upload limit, the attempt SHALL fail
without downloading: the task ends in `error` with a stored reason, and no
automatic retry is scheduled.

#### Scenario: Estimate over the limit

- **WHEN** a Telegram-origin task starts and the estimated size for the selected quality exceeds the configured upload limit
- **THEN** no download transfer is started
- **AND** the task ends in `error` with a stored reason stating that the file is too large for Telegram
- **AND** no automatic retry is scheduled

#### Scenario: Estimate within the limit

- **WHEN** a Telegram-origin task starts and the estimated size for the selected quality is at or below the configured upload limit
- **THEN** the download proceeds as usual

#### Scenario: Size cannot be estimated

- **WHEN** a Telegram-origin task starts and the metadata provides no usable size estimate for the selected quality
- **THEN** the download proceeds as usual

#### Scenario: Non-Telegram task is not restricted

- **WHEN** a Download Task that did not originate in Telegram is processed
- **THEN** the size estimate is not applied and the download proceeds regardless of the estimated size

#### Scenario: The limit follows configuration

- **WHEN** the configured Telegram upload limit changes
- **THEN** subsequent attempts are judged against the new limit

### Requirement: The requester is told to choose another quality

When a Telegram-origin task is rejected by the size guard, the bot SHALL send a
message that states the file at the selected quality is too large for Telegram
and asks the requester to choose another quality. The message SHALL offer no
Re-download action, and SHALL NOT include internal downloader details.

#### Scenario: Size rejection message

- **WHEN** a Telegram-origin task is rejected by the size guard
- **THEN** the requester receives a message naming the size problem and asking for another quality

#### Scenario: No Re-download action on size rejection

- **WHEN** a Telegram-origin task is rejected by the size guard
- **THEN** the failure message offers no Re-download action, because repeating the same quality would fail again

## MODIFIED Requirements

### Requirement: The requester is told that a download failed

When a Telegram-origin task ends in `error` for any reason other than the size
rejection, the bot SHALL send a generic failure message that does not include
the recorded reason, together with a Re-download action and a link to the web
library. A task rejected by the size guard SHALL instead receive its own
actionable message without a Re-download action. The recorded reason SHALL
remain available to administrators in every case.

#### Scenario: Final Telegram failure

- **WHEN** a Telegram-origin task ends in `error` for a reason other than the size rejection
- **THEN** the bot sends a generic failure message
- **AND** the message does not include the recorded reason
- **AND** the message offers a Re-download action
- **AND** the message points to the web library

#### Scenario: Pressing Re-download in Telegram

- **WHEN** the requester presses Re-download for one of their error tasks
- **THEN** the task returns to `queued` as a fresh attempt
- **AND** a new message is dispatched for it

#### Scenario: Size rejection does not use the generic message

- **WHEN** a Telegram-origin task ends in `error` because of the size guard
- **THEN** the bot sends the size-rejection message instead of the generic failure message
