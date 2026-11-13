# Tasks

## 1. Clear action

- [x] 1.1 Add a bulk delete of `error` tasks to the Download Task repository;
      verify with a repository unit test that only error rows are deleted and
      queued/processing rows survive.
- [x] 1.2 Add the POST action on the admin dashboard with CSRF check, success
      flash carrying the cleared count, and an audit event (plus the new audit
      action enum case and its en/ru labels); verify with functional tests that
      clearing deletes error tasks, keeps the others, redirects with the flash,
      and that a missing token deletes nothing and records a failed audit
      attempt.

## 2. Page control

- [x] 2.1 Add the confirmation-guarded button to the Active Tasks template,
      shown only while the error counter is above zero, with en/ru labels,
      title and confirmation text; verify with a functional test that the
      control is offered while error tasks exist.

## 3. Integration checks

- [x] 3.1 Full test suite passes (`make test`).
- [x] 3.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 3.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase first,
  commit and push).
