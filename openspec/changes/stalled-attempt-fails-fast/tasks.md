# Tasks

## 1. Stall watchdog in the wrapper

- [x] 1.1 Replace the blocking output loop with a polling loop: drain
      incremental output, take a staging-directory signature, abort the
      run when nothing changed for the stall window (constructor
      parameter, one minute by default) with a permanent-classified
      reason; keep the process timeout behavior; verify with wrapper
      tests: a silent run fails fast with the stall reason, a growing
      file keeps the run alive, and progress flags reach the command.
- [x] 1.2 Classifier test: the stall reason is permanent (no automatic
      retry, so the notification goes out after the first stalled minute).

## 2. Progress output restored

- [x] 2.1 Enable the downloader progress bar (`--progress`) so the
      existing `PROGRESS:` parsing and the specified Telegram progress
      ticks work again; verify with a command-argument test and keep the
      existing progress-parsing tests green.

## 3. Integration checks

- [x] 3.1 Full test suite passes (`make test`).
- [x] 3.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 3.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
