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
