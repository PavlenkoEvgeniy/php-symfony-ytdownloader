# Spec Delta

## ADDED Requirements

### Requirement: Telegram downloads report periodic progress

While a Telegram-origin Download Task is processing, the bot SHALL report its
download progress at the configured interval, as a whole percentage. The progress
SHALL be shown by creating one message and editing it for subsequent updates, not
by sending a new message each time.

#### Scenario: Progress tick during a download

- **WHEN** a Telegram-origin download is still running when the configured interval elapses
- **THEN** the bot shows the current progress as a whole percentage
- **AND** later updates edit the same message

#### Scenario: Short download

- **WHEN** a Telegram-origin download finishes before the first interval elapses
- **THEN** no progress message is sent

#### Scenario: Download reaches completion

- **WHEN** a progress message exists and the download reaches 100 percent
- **THEN** the message is updated to 100 percent without waiting for the next interval

### Requirement: The progress interval is configurable

The Admin Panel SHALL provide a setting for the progress interval in minutes. The
default SHALL be 5 minutes, and zero SHALL disable progress reporting.

#### Scenario: Default interval

- **WHEN** no administrator has changed the setting
- **THEN** Telegram downloads report progress every 5 minutes

#### Scenario: Administrator changes the interval

- **WHEN** an administrator sets the progress interval
- **THEN** downloads started afterwards use the new interval
- **AND** the change is recorded in the audit log

#### Scenario: Progress disabled

- **WHEN** the progress interval is zero
- **THEN** no progress messages are sent

### Requirement: Progress is Telegram-only and best-effort

Progress reporting SHALL apply only to tasks created through Telegram. A failure
to send or edit a progress message SHALL NOT change the download outcome.

#### Scenario: Web or API download

- **WHEN** a download created through the web UI or REST API is processing
- **THEN** no progress message is sent

#### Scenario: Telegram progress message fails

- **WHEN** sending or editing the progress message fails
- **THEN** the download continues normally
