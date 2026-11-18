# Tasks

## 1. Asynchronous acknowledgement

- [x] 1.1 Add `TelegramUpdateMessage`, its handler on the async transport
      and route it in messenger config; the hook controller dispatches the
      message and answers 200 without calling the update handler, 500 when
      dispatching fails; verify with the re-targeted hook controller tests.
- [x] 1.2 Deduplicate redelivered updates by `update_id` in the message
      handler (cache, 30 minutes, marked before handling); verify with
      handler tests: first delivery handled once, redelivery skipped, a
      payload without an id unchanged.

## 2. Integration checks

- [x] 2.1 Full test suite passes (`make test`).
- [x] 2.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 2.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- After the deploy, drop Telegram's pending update backlog (stale
  redeliveries of the incident) with the hook registration command.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
