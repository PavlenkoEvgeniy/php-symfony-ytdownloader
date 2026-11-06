# Spec Delta

## ADDED Requirements

### Requirement: Reset only touches stuck tasks

Reset SHALL return a `processing` task to the queue only when no download attempt
is live for that task. A task whose attempt is still running SHALL stay
`processing` and SHALL NOT be started a second time.

#### Scenario: Resetting a live download

- **WHEN** an administrator runs Reset while a worker is downloading a processing task
- **THEN** that task stays `processing`
- **AND** no second attempt is started for it

#### Scenario: Resetting a dead attempt

- **WHEN** an administrator runs Reset for a processing task whose worker died and whose attempt is no longer live
- **THEN** the task returns to `queued`
- **AND** a new message is dispatched for it

#### Scenario: Skipped live tasks are reported

- **WHEN** Reset skips one or more live tasks
- **THEN** the admin flash and the audit event report how many tasks were skipped

### Requirement: A task is worked on by one live attempt

Only one live download attempt SHALL exist per Download Task. A message delivered
for a task that already has a live attempt SHALL be ignored. A message delivered
for a `processing` task with no live attempt SHALL be allowed to re-run the
download, so redeliveries and retries are not silently skipped.

#### Scenario: Duplicate delivery while an attempt is live

- **WHEN** two messages for the same task are delivered at the same time
- **THEN** only one of them starts a download attempt

#### Scenario: Redelivery after a dead attempt

- **WHEN** a `processing` task has no live attempt and its message is delivered again
- **THEN** the download is attempted again
- **AND** the attempt counter is incremented

### Requirement: Long downloads are not cut off by a short fixed timeout

The download process SHALL be bounded by a configurable timeout whose default is
6 hours, so a legitimate long download is not interrupted after 5 minutes. The
probe used by the Telegram bot SHALL keep a short timeout.

#### Scenario: Download longer than five minutes

- **WHEN** a download is still running after five minutes
- **THEN** it continues until it completes or reaches the configured download timeout

#### Scenario: Reaching the download timeout

- **WHEN** a download reaches the configured timeout
- **THEN** the attempt ends with an error reason
- **AND** the downloader process is terminated

### Requirement: An attempt finishes its process before finalizing the task

When an attempt ends, the downloader process SHALL be terminated before the task
status is finalized, so it cannot keep writing files after the task is reported
as finished.

#### Scenario: Attempt ends with a failure

- **WHEN** a download attempt ends with an error
- **THEN** the downloader process no longer writes to the downloads directory
- **AND** the task is marked `error`
