# Tasks

## 1. /info command

- [x] 1.1 Handle /info in the update handler: reply with the APP_VERSION
      and the last deployment time from the `.env` mtime in UTC, degrade to
      "unknown" when unreadable; register /info in the command menu and
      the /help text; verify with handler tests (reply content with a
      fixed mtime, unreadable file) and the menu test.

## 2. Integration checks

- [x] 2.1 Full test suite passes (`make test`).
- [x] 2.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 2.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
