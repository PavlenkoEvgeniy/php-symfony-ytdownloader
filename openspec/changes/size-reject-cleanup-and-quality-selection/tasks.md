# Tasks

## 1. Staged cleanup of failed attempts

- [x] 1.1 Stage every wrapper run in a private directory of the downloads
      directory, move reported files to the downloads directory after the
      run and always remove the staging directory; sweep staging directories
      older than twice the process timeout at run start; verify with wrapper
      tests that a cap refusal, a failure and a timeout leave nothing behind,
      that a successful run's file lands in the downloads directory, and
      that a stale staging directory is swept.

## 2. Quality selection after the rejection

- [x] 2.1 Add the quality-selection offer to the Telegram update handler
      (interface + implementation): pending token, per-quality size
      filtering against the configured limit, rejected quality skipped,
      "nothing fits" message when the selection is empty; en/ru labels.
- [x] 2.2 Call the offer from the size-rejection path of the download
      handler after the rejection message; verify with functional tests:
      selection offered and filtered, picked quality queued, empty
      selection answered, and the existing rejection-message tests stay
      green.

## 3. Integration checks

- [x] 3.1 Full test suite passes (`make test`).
- [x] 3.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 3.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Delete the leftover file found on production in `var/downloads` (verify
  first that no Source references it).
- Commit, push, open a PR, merge it, and cut the release tag.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
