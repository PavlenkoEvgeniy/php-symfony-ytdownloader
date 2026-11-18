# Tasks

## 1. Atomic update claim

- [x] 1.1 Migration: `telegram_update_dedupe (update_id BIGINT PRIMARY
      KEY, seen_at TIMESTAMPTZ DEFAULT NOW())`.
- [x] 1.2 The message handler claims updates with a single
      `INSERT … ON CONFLICT DO NOTHING` through the database connection,
      prunes rows older than seven days after a winning claim, and drops
      the cache dependency; verify with handler tests: a winning insert is
      handled once, a duplicate insert is skipped, an id-less payload is
      handled without database calls.

## 2. Integration checks

- [x] 2.1 Full test suite passes (`make test`).
- [x] 2.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 2.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag together with
  the flood-guard change.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
