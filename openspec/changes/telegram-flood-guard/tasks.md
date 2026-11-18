# Tasks

## 1. Flood ceiling

- [x] 1.1 Guard every outbound chat send (text, media, edits) in the
      Telegram client with a per-chat fixed-window ceiling of ten per
      minute held in the shared cache; dropped sends return zero and are
      logged. Verify with client tests: a burst within the ceiling is
      delivered, sends beyond it never hit the HTTP client, and each drop
      is logged.
- [x] 1.2 Extend the update-id deduplication window from 30 minutes to
      24 hours (a late redelivery must not repeat an accepted update).

## 2. Integration checks

- [x] 2.1 Full test suite passes (`make test`).
- [x] 2.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 2.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
