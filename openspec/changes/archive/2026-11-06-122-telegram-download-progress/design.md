# Design

## Context

`YoutubeDlWrapper` currently calls the blocking `P3s\YtDlp\YtDlpClient`, which
buffers stdout/stderr until the process exits. There is no callback, so progress
cannot be observed in real time. The Telegram bot already has a link between a
task and its chat (`telegram_chat_id`), and `DownloadSettings` is the established
singleton for runtime admin settings.

## Goals / Non-Goals

**Goals:**

- Show a Telegram user how far their download is, in whole percent.
- Use one message that is edited in place.
- Make the interval configurable and disableable from the Admin Panel.
- Keep the download independent of Telegram availability.

**Non-Goals:**

- Progress for web/API downloads or in the admin panel.
- Speed/ETA/bytes, multiple progress messages, progress for playlists.
- Changing the final file delivery flow.

## Decisions

- **Stream in the app wrapper.** `YoutubeDlWrapper` builds the yt-dlp command and
  runs it with Symfony Process (`start()` + iteration), passing `--newline` and
  `--progress-template "download:PROGRESS:%(progress._percent_str)s"`. This keeps
  the download options in one place and avoids a blocking call. The probe keeps
  using the package's `YtDlpClient` and is unaffected. Upstreaming a streaming
  method to `p3sdev/php-ytdlp-wrapper` is left as a follow-up because the app
  needs the change now and the package has no callback API.
- **Parser.** Progress lines may be split across process chunks and may be
  separated by `\r` or `\n`; the wrapper keeps a small buffer, extracts the
  percentage and calls the callback only when the rounded integer changes.
- **Reporter per attempt.** `DownloadProgressNotifier::start()` returns `null`
  when the task is not from Telegram, has no chat id, or the interval is zero.
  Otherwise it creates a `TelegramProgressReporter` with the start time, the
  interval and a logger. The first message is sent after the first interval, so a
  short download never creates one; an existing message is updated immediately at
  100 %.
- **Edit in place, fall back to a new message.** `TelegramClient::editMessageText`
  is used after the first send. If editing fails (message deleted, API error), the
  reporter sends a fresh message and keeps its id for later edits.
- **Best-effort.** Every Telegram call is wrapped; a failure is logged and the
  callback returns, so the download outcome cannot change.
- **Setting.** `DownloadSettings.progressIntervalMinutes` is an integer column
  with default 5. Zero disables progress. The form accepts 0–1440; the settings
  controller includes the value in the audit metadata. `getSettings()` returns an
  unsaved entity with the default when the row is missing.
- **Where the reporter is created.** `VideoDownloaderService` knows the task, chat
  id and title, and calls the notifier immediately before the wrapper download;
  the wrapper only knows percentages.
