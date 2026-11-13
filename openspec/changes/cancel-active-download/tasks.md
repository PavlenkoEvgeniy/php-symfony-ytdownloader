# Tasks

## 1. Cancel an active download

- [x] 1.1 Repository: `deleteActiveByUser` (queued + processing) and a
      scalar `existsById` that bypasses the identity map; verify with
      repository tests.
- [x] 1.2 Wrapper: optional cancellation closure polled in the existing
      loop — on abort stop the process, relocate finished files and
      report a cancellation reason; verify with a wrapper test that an
      aborting closure stops a run quickly and leaves the downloads
      directory empty.
- [x] 1.3 Service: pass the cancellation closure (task row gone) into the
      wrapper; keep the five `download()` mock expectations green by
      adding the new argument matcher.
- [x] 1.4 Handler: /cancel removes active tasks and answers with the
      cancelled count (wording without "queued"); verify the updated
      cancel tests and a handler test that a task removed mid-attempt
      produces no notification.

## 2. Integration checks

- [x] 2.1 Full test suite passes (`make test`).
- [x] 2.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 2.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
