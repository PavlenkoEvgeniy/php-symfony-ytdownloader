# Proposal

## Why

After an administrator resets a "stuck" task, the old download can keep running in
the background while a second attempt starts, so the disk keeps shrinking after
the task is already marked as an error (GitHub #120). The reset action cannot tell
a task whose worker is alive from one whose worker died, and the fixed 300-second
yt-dlp timeout interrupts legitimate long downloads, leaving a failed attempt
whose process is not always reconciled with the task.

## What Changes

- A processing attempt holds a per-task lease. Reset only returns tasks to the
  queue when their lease is free; live downloads are skipped and reported.
- A message delivery may reclaim a `processing` task when no attempt is live, so
  retries and redeliveries actually re-run the download instead of being skipped.
- The downloader process timeout becomes a long, configurable bound (6 hours by
  default) instead of 5 minutes, and every attempt terminates its downloader
  process before the task status is finalized.
- Reset reports how many live tasks were skipped in the admin flash and audit
  event.

## Capabilities

### New Capabilities

- `queue`: liveness of processing attempts, including what Reset may and may not
  touch.

### Modified Capabilities

_None._

## Impact

- `DownloadMessageHandler`, `QueueResetService`, `DownloadTaskManager`,
  `DownloadTaskRepository`, the Active Tasks admin page, and translations.
- `YoutubeDlWrapper` runs yt-dlp through a streaming Process with a long timeout
  (`yt_dlp.timeout`, default 6 hours); the probe client keeps its 20-second
  timeout.
- Out of scope: automatic retries of failed downloads (change
  `121-download-failure-recovery`), Telegram progress reporting (change
  `122-telegram-download-progress`), and cancelling an already running download
  from the admin page.
