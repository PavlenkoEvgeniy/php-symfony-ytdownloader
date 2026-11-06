# download-recovery Specification

## Purpose
Download recovery defines what happens after a download attempt fails: the stored
reason, bounded automatic retries for transient failures, the requester
notification and the user- or admin-initiated Re-download.

## Requirements

### Requirement: A failed attempt records its reason

Every failed download attempt SHALL store a concise, human-readable reason on the
Download Task, replacing any previous reason when a new attempt starts.

#### Scenario: yt-dlp reports a failure

- **WHEN** an attempt fails
- **THEN** the task stores a concise reason derived from the downloader output

### Requirement: Transient failures are retried automatically

The service SHALL classify a failure as transient or permanent. A transient
failure SHALL return the task to the queue and schedule another attempt while the
attempt limit is not reached; a permanent failure SHALL NOT be retried.

#### Scenario: Transient failure below the attempt limit

- **WHEN** an attempt fails for a transient reason and the attempt limit is not reached
- **THEN** the task returns to `queued`
- **AND** a new attempt is scheduled
- **AND** the requester is not told about the intermediate failure

#### Scenario: Transient failure after the attempt limit

- **WHEN** the last allowed attempt fails for a transient reason
- **THEN** the task is marked `error`
- **AND** the requester is notified

#### Scenario: Permanent failure

- **WHEN** an attempt fails for a permanent reason
- **THEN** the task is marked `error` without another automatic attempt

### Requirement: The requester is told why a download failed

When a Telegram-origin task ends in `error`, the bot SHALL send the recorded
reason together with a Re-download action.

#### Scenario: Final Telegram failure

- **WHEN** a Telegram-origin task ends in `error`
- **THEN** the bot sends the reason
- **AND** the message offers a Re-download action
- **AND** the message points to the web library

#### Scenario: Pressing Re-download in Telegram

- **WHEN** the requester presses Re-download for one of their error tasks
- **THEN** the task returns to `queued` as a fresh attempt
- **AND** a new message is dispatched for it

### Requirement: An administrator can re-download an error task

The Admin Panel SHALL offer Re-download for tasks in `error` status.

#### Scenario: Admin re-downloads an error task

- **WHEN** an administrator triggers Re-download for an error task
- **THEN** the task returns to `queued` as a fresh attempt
- **AND** the action is recorded in the audit log

#### Scenario: Re-download is only for error tasks

- **WHEN** Re-download is requested for a task that is not in `error` status
- **THEN** nothing is requeued

### Requirement: Re-download starts a fresh attempt

Re-download SHALL clear the previous error and finish time and SHALL reset the
attempt counter, so the task is indistinguishable from a newly accepted request
except for its history in the audit log. A successful attempt SHALL clear the
stored error reason.

#### Scenario: Re-downloaded task succeeds

- **WHEN** a re-downloaded task completes successfully
- **THEN** the task is `success`
- **AND** no previous error reason remains on it
