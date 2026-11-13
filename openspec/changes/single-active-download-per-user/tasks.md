# Tasks

## 1. One active download per Telegram user

- [x] 1.1 Enforce the single active slot on the link intake with
      `findActiveByUser` and the shared wait-or-cancel hint; verify with a
      handler test that a second link is refused before the probe and the
      hint is sent.
- [x] 1.2 Guard the Re-download callback with the same slot check; verify
      with a handler test that the retry is refused with the hint and the
      retry service is never called, while the existing requeue test stays
      green.

## 2. Integration checks

- [x] 2.1 Full test suite passes (`make test`).
- [x] 2.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 2.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
