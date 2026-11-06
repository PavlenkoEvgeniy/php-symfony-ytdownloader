# Proposal

## Why

A Telegram user who sends a video sees no feedback while a long download runs, so
a slow or stuck download is indistinguishable from a broken bot (GitHub #122). The
downloader already parses its own progress output, but it is produced only when
the process finishes and is never shown to the user.

## What Changes

- Downloads requested through Telegram report their progress every configured
  interval (5 minutes by default) as a whole percentage.
- One Telegram message is created and edited in place for the whole download, so
  the chat is not flooded.
- The Admin Panel gains a setting for the progress interval; zero disables
  progress reporting.
- The yt-dlp wrapper streams progress lines while the process runs instead of
  buffering the output until the end.
- Progress reporting is best-effort: a Telegram failure never changes the
  download outcome.

## Capabilities

### New Capabilities

- `download-progress`: periodic progress reporting for Telegram downloads and its
  admin setting.

### Modified Capabilities

_None._

## Impact

- `YoutubeDlWrapper` and `VideoDownloaderService`, a new progress notifier, the
  `DownloadSettings` entity/form/admin page, a database migration, translations
  and tests.
- Out of scope: progress for web/API downloads, progress in the admin panel,
  changing the Telegram delivery flow, and the liveness/timeout work in
  `120-stuck-download-liveness`.
