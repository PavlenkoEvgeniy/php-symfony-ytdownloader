# Design

## Context

The downloader returns only `SUCCESS` or `ERROR`; the handler marks the task
`error` without a reason, and `VideoDownloaderService` sends a generic Telegram
message. The task's `attempts` counter is incremented by `claimProcessing`, but a
retried message is currently skipped because the task is still `processing`; the
liveness work in `120-stuck-download-liveness` makes retries possible. Messenger
already retries thrown exceptions, but wrapper failures are returned values, not
exceptions, so automatic retries need an explicit decision in the handler.

## Goals / Non-Goals

**Goals:**

- Store a useful reason for every failed attempt.
- Retry transient failures automatically and stop on permanent ones.
- Give the requester and the administrator a manual Re-download action.
- Keep the task lifecycle (`queued` → `processing` → `success` | `error`) intact.

**Non-Goals:**

- Immediate deletion of partial files (the hourly orphan pruner handles them).
- Changing the web/API request forms.
- A user-facing retry history beyond the existing audit log.

## Decisions

- **Result instead of an enum.** `VideoProcessorInterface::process()` returns a
  `DownloadResult` with the outcome, an optional reason and a failure kind. The
  enum alone cannot carry the reason to the handler, and a mutable "last error"
  on the service would be unsafe for a long-running worker.
- **Classifier.** `DownloadFailureClassifier` turns yt-dlp output into
  `transient` or `permanent`. Transient patterns are network/timeouts and HTTP
  429/5xx; known permanent patterns are unsupported/private/unavailable video,
  format unavailable and sign-in requirements. The default is **permanent**, so
  an unknown failure is retried only by a human.
- **Automatic retry in the handler.** A transient failure below the attempt limit
  moves the task back to `queued` and dispatches a new `DownloadMessage` with a
  `DelayStamp` (60 s, 120 s, 240 s). The attempt limit is 3. The task is not
  marked `error` and the requester is not notified until the limit is reached, so
  an intermediate failure does not look final.
- **Unexpected exceptions.** Messenger's own retry and the failure listener still
  apply. Thanks to the lease/claim change, the retried delivery can actually run;
  the listener marks the task `error` when retries are exhausted.
- **Reason extraction.** The first meaningful line of yt-dlp output, with the
  `ERROR:` prefix removed and truncated to 500 characters, is stored in
  `error_message` and sent to the requester. A technical yt-dlp line is more
  useful than a generic "something went wrong".
- **Re-download is a separate service.** `DownloadRetryService` only accepts an
  `error` task, clears the error/finish time, resets `attempts` to zero, sets
  `queued` and dispatches a new message. Keeping this out of `DownloadTaskManager`
  lets both the admin and the Telegram callback reuse it and audit with the right
  actor and channel.
- **Telegram callback.** The final-failure message carries an inline button
  `retry:<taskId>`. The handler verifies that the task is an `error` task of the
  linked user before re-downloading; the callback answer reports success or that
  the task is no longer retryable.
- **Audit.** A new `download.retried` action records actor, channel, task and URL.
  Intermediate automatic retries are metadata on the failed-attempt event, not a
  new action.
