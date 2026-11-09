# Proposal

## Why

When a Telegram download fails, the bot sends the raw downloader reason, for
example `Could not download the video: unable to download video data: HTTP
Error 403: Forbidden`. The reason is technical noise for the requester, it may
expose internal details, and it does not tell the requester what to do next. The
recorded reason remains useful for the administrator, so it should stay on the
task without being sent to the requester (GitHub #129).

## What Changes

- The Telegram failure message shows a generic text, `Download failed. Please
  try again later.`, instead of the recorded reason.
- The recorded reason is still stored on the Download Task and shown in the
  Admin Panel.
- The message keeps the Re-download action and the link to the web library.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `download-recovery`: the requester is told that a download failed, without the
  internal reason.

## Impact

- The Telegram notifier and its call sites in the message handler and the
  failure listener; translations.
- No data change: the reason is still recorded on the task.
- Out of scope: the reason shown in the Admin Panel and the web UI.
