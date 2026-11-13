# Tasks

## 1. Size estimation

- [x] 1.1 Build a size-estimation service that runs the downloader metadata call
  with the resolved format selector (bounded socket timeout) and sums exact or
  approximate sizes per selected format, per playlist entry; verify with unit
  tests covering exact size, approximate fallback, playlist entries, and a
  probe failure yielding "no estimate" (design decision 2, 6).
- [x] 1.2 Verify against a real URL inside the php container that the metadata
  call with the format selector exposes the selected formats with sizes, and
  adjust the field mapping if the shape differs (verify: the command output
  shows summed bytes for a known video).

## 2. Pre-transfer rejection

- [x] 2.1 Extend the download result so a size rejection is a distinct,
  machine-checkable outcome alongside the stored reason; verify with unit tests
  for the new factory and the transient/permanent kind (design decision 3, 5).
- [x] 2.2 Wire the guard into `VideoDownloaderService` for Telegram-origin tasks
  only: estimate after quality resolution, compare against
  `TELEGRAM_MAX_UPLOAD_MB`, return the size-rejection result without invoking
  the downloader when over the limit; verify with unit tests for over-limit
  (downloader not called), within the limit, unknown estimate, non-Telegram
  task, and a probe exception falling through (spec: Oversized Telegram
  downloads are rejected before transfer).

## 3. Requester message

- [x] 3.1 Add the `telegram.file_too_large` translation keys (en + ru) and a
  dedicated notifier method that sends the size problem plus "choose another
  quality" without a Re-download button; verify with unit tests asserting the
  text and the absent reply markup (spec: The requester is told to choose
  another quality).
- [x] 3.2 Route size rejections in `DownloadMessageHandler` to the new notifier
  instead of the generic failure message, keeping `markError` and skipping
  retry scheduling; verify with handler tests for the size-rejection branch and
  the unchanged generic branch (spec MODIFIED: The requester is told that a
  download failed).

## 4. Documentation

- [x] 4.1 Record the actionable-message exception in ADR-0017 and mention the
  pre-transfer guard next to `TELEGRAM_MAX_UPLOAD_MB` in README; verify both
  files state the exception and the guard explicitly.

## 5. Integration checks

- [x] 5.1 Run `make lint` (cs-fix, phpstan, psalm, peck) and confirm it passes
  with no baseline growth.
- [x] 5.2 Run `make test` and confirm the full suite passes.
- [x] 5.3 Run `make openspec-validate` and confirm strict validation passes.

## Workflow follow-up

- Journal the session in `$TASKS_DIR/ytdownloader/_local.md` (decisions, next
  step); the user reviews the diff, commits and pushes this repository.
- `/opsx:archive` after review and merge.
