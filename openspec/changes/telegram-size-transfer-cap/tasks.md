# Tasks

## 1. Transfer cap

- [x] 1.1 Add an optional transfer-cap argument to the downloader wrapper so a
  Telegram-origin call passes the configured limit, and recognize the
  downloader's cap refusal (exit 0 with no file, and as the failure reason on
  the error path) as the error reason carrying the refusal line; verify with
  wrapper tests for both paths and for the unchanged no-cap behavior
  (design decision 1, 2).
- [x] 1.2 In the download service, pass the cap only for Telegram-origin tasks
  and map a refusal reason to the existing size-rejection outcome (stored
  reason with real sizes, permanent, no retry); verify with service tests for
  the refusal branch and for non-Telegram tasks staying uncapped
  (spec: Oversized Telegram downloads are rejected before transfer).

## 2. Final guard

- [x] 2.1 After a Telegram download completes, delete a resulting file that
  exceeds the limit and return the size-rejection outcome without creating
  library content; verify with a service test asserting the file removal, no
  library record, and the rejection result (spec: A completed oversized file
  is not kept).
- [x] 2.2 Confirm the requester message path (dedicated size message, no
  Re-download) is reused for both new rejection points; verify with handler
  tests for a transfer-cap refusal reaching the dedicated message (spec
  MODIFIED: The requester is told to choose another quality).

## 3. Documentation

- [x] 3.1 Update the README size-guard paragraph to describe the layers
  (metadata estimate, transfer cap, final guard) against the same
  `TELEGRAM_MAX_UPLOAD_MB`; verify the paragraph names all three and the limit.

## 4. Integration checks

- [x] 4.1 Run `make lint` (cs-fix, phpstan, psalm, peck) and confirm it passes
  with no baseline growth.
- [x] 4.2 Run `make test` and confirm the full suite passes.
- [x] 4.3 Run `make openspec-validate` and confirm strict validation passes.

## Workflow follow-up

- Journal the session in `$TASKS_DIR/ytdownloader/_local.md` (decisions, next
  step).
- Commit, push, open the PR, merge, release; then `/opsx:archive`.
