# Tasks

## 1. Order-aware selection filter

- [x] 1.1 Replace the per-quality judgement in the quality offer with a
      single cut-off index over the ordered quality list (rejected index
      and over-limit estimate indices), probing only the qualities after
      the rejected one; verify with handler tests: a larger quality with
      an unknown estimate is not offered after a rejection, an over-limit
      quality takes its larger neighbours out, the remaining list keeps
      the size order, and the existing filter/no-fit tests stay green.

## 2. Integration checks

- [x] 2.1 Full test suite passes (`make test`).
- [x] 2.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 2.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
