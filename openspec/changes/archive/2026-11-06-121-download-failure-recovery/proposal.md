# Proposal

## Why

A failed download currently ends with a generic Telegram message and no way to
run it again; the reason is stored only for unexpected exceptions and is visible
only in the admin panel. Transient failures (network timeouts, HTTP 429/5xx) are
not retried, while a permanent failure (private, unavailable or unsupported
video) is indistinguishable from them (GitHub #121).

## What Changes

- Every failed attempt records a concise, human-readable reason on the task.
- Failures are classified as transient or permanent. Transient failures are
  retried automatically up to a bounded number of attempts; permanent failures
  are not retried.
- After the final failure the requester receives the reason in Telegram with a
  **Re-download** button; the Admin Panel offers the same action on error tasks.
- Re-download puts an error task back in the queue as a fresh attempt, clears the
  error and resets its attempt counter, and is recorded in the audit log.
- A successful retry clears the previous error state.

## Capabilities

### New Capabilities

- `download-recovery`: what happens after an attempt fails, including automatic
  retry, the recorded reason, user notification and manual re-download.

### Modified Capabilities

_None._

## Impact

- `VideoProcessorInterface` returns a result with outcome, reason and failure
  kind; `VideoDownloaderService`, `DownloadMessageHandler`,
  `DownloadTaskManager`, `DownloadTaskFailureListener`, `TelegramNotifier`,
  `TelegramUpdateHandler`, the Active Tasks admin page and translations.
- New audit action for a re-download, new repository/service seams and tests.
- Out of scope: cleaning partial files immediately after a failure (the hourly
  orphan pruner keeps handling them), changing the web or API download forms, and
  the liveness/reset work in `120-stuck-download-liveness`.
