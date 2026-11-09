# Transient failures retry automatically; users can re-download errors

A failed download ended with a generic Telegram message, no stored reason for
ordinary wrapper failures, and no way to run it again (GitHub #121). Network
outages and HTTP 429/5xx are worth another attempt, while a private or
unsupported video is not.

`VideoProcessorInterface` now returns a `DownloadResult` with the reason and a
`DownloadFailureKind`. A `DownloadFailureClassifier` maps yt-dlp output to
`transient` or `permanent`; unknown failures are permanent on purpose. The
handler returns a transient task to `queued` and dispatches a delayed message
(60/120/240 s) until three attempts are used, then marks it `error` and notifies
the requester. A permanent failure is marked `error` immediately. Unexpected
exceptions keep using Messenger's retry; thanks to ADR 0013 the retried delivery
can actually claim the task.

The final Telegram failure message carries a **Re-download** button; the
recorded reason stayed in the message until ADR 0017 hid it. `DownloadRetryService`
accepts only `error` tasks, clears the error and finish time, resets the attempt
counter, queues the task and audits the action.
The Admin Panel exposes the same action. Intermediate automatic retries are
audited as failed attempts with `will_retry: true`, not as a separate action.

This supersedes the incidental statement in ADR 0002 that no retry mechanism is
planned; purge still deletes queued tasks and messages without retrying them.

## Considered Options

- **Rely on Messenger retries for everything** — wrapper failures are returned
  values, not exceptions, and Messenger's default backoff is too short for a
  network outage.
- **Retry every failure** — a private or unsupported video would loop without
  ever succeeding.
- **Only an admin retry** — leaves the requester without an explanation or a way
  to act, and Telegram is the surface where the failure is felt.
- **Immediate partial-file cleanup** — files cannot be attributed to an attempt
  today; the hourly orphan pruner keeps handling them.

## Consequences

- Every error task stores a concise reason, visible in the Admin Panel.
- A wrong link costs one attempt, not three.
- A user pressing Re-download starts a fresh attempt with a reset counter.
- Partial files from failed attempts remain until the hourly orphan prune.
