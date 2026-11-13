# Spec Delta

## MODIFIED Requirements

### Requirement: Oversized Telegram downloads are rejected before transfer

For a Telegram-origin Download Task, the service SHALL keep the download within
the configured Telegram upload limit, checking the size as early as it becomes
known. A download that would exceed the limit SHALL NOT complete: no file is
produced or kept, the task ends in `error` with a stored reason, and no
automatic retry is scheduled. A completed oversized file SHALL be removed
rather than kept as library content.

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

#### Scenario: The size is revealed when the transfer starts

- **WHEN** a Telegram-origin task starts, metadata has no usable estimate, and the size revealed at transfer start exceeds the configured upload limit
- **THEN** the transfer is aborted without producing a file
- **AND** the task ends in `error` with a stored reason stating that the file is too large for Telegram
- **AND** no automatic retry is scheduled

#### Scenario: A completed oversized file is not kept

- **WHEN** a Telegram download completes and the resulting file exceeds the configured upload limit
- **THEN** the file is removed and is not kept as library content
- **AND** the task ends in `error` with a stored reason stating that the file is too large for Telegram
- **AND** no automatic retry is scheduled

#### Scenario: Non-Telegram task is not restricted

- **WHEN** a Download Task that did not originate in Telegram is processed
- **THEN** the size checks are not applied and the download proceeds regardless of the estimated size

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

#### Scenario: Rejection discovered during the transfer

- **WHEN** a Telegram-origin task is rejected because the size only became known at transfer start or after completion
- **THEN** the requester receives the same size message asking for another quality
