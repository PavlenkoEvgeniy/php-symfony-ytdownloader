# Tasks

## 1. Streaming downloader

- [x] 1.1 Run yt-dlp through Symfony Process with `--newline` and a progress template
- [x] 1.2 Parse progress from `stdout`/`stderr` chunks and invoke a callback on whole-percent changes
- [x] 1.3 Keep the existing error/no-files behavior and the long timeout

## 2. Telegram progress

- [x] 2.1 Add the progress notifier/reporter with interval throttling and edit-in-place
- [x] 2.2 Create the reporter in `VideoDownloaderService` only for Telegram-origin tasks
- [x] 2.3 Make Telegram failures non-fatal and log them

## 3. Admin setting

- [x] 3.1 Add `progressIntervalMinutes` to `DownloadSettings` with default 5 and a migration
- [x] 3.2 Add the form field, hint and audit metadata
- [x] 3.3 Add translations (en/ru)

## 4. Tests and verification

- [x] 4.1 Unit tests for progress parsing and reporter throttling/editing
- [x] 4.2 Settings controller test for the interval and its audit metadata
- [x] 4.3 Run `make lint`, `make test`, `make openspec-validate`

## 5. OpenSpec

- [x] 5.1 Sync the spec and archive the change
