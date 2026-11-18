# Tasks

## 1. Polling transport

- [x] 1.1 Add the `app:telegram-poll` command: long-poll `getUpdates` on
      the Bot API server, dispatch each update as `TelegramUpdateMessage`,
      persist the offset after each batch, peek the newest update on a cold
      start, back off on failures; `--once` for a single cycle. Verify with
      command tests: cold start primes after the newest update and
      dispatches the batch, a stored offset resumes without priming, an
      empty batch dispatches nothing.
- [x] 1.2 Add the supervisord program (`numprocs=1`, autorestart) so the
      poller starts with the container; it ships through the mounted
      `conf.d` directory.

## 2. Integration checks

- [x] 2.1 Full test suite passes (`make test`).
- [x] 2.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 2.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- After the deploy: verify the poller runs and the bot answers; clear the
  webhook backlog question once Telegram's cloud endpoint recovers.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
