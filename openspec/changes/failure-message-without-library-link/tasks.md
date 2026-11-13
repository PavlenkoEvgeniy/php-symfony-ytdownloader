# Tasks

## 1. Recognize a quiet cap refusal

- [x] 1.1 Report an empty successful yt-dlp run under a transfer cap as a cap
      refusal (refusal-style reason containing `larger than max-filesize`);
      verify with a new wrapper unit test where the fake binary exits 0 printing
      nothing and a cap is passed, plus the existing no-cap test still asserting
      the generic "no downloaded file was reported" reason.
- [x] 1.2 Map a refusal reason without byte sizes to the limit-only
      size-rejection message; verify with a new service unit test asserting the
      exact fallback reason "The file exceeds the Telegram upload limit of N MB.
      Choose another quality." and a `sizeExceeded` result.

## 2. Generic failure message without the library URL

- [x] 2.1 Stop appending the web-library URL to the generic Telegram failure
      message; verify with the updated notifier unit test expecting the plain
      generic text with the Re-download button and no URL.

## 3. Integration checks

- [x] 3.1 Full test suite passes (`make test`).
- [x] 3.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 3.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR, merge it, and cut the release tag.
- Archive the change (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase first,
  commit and push).
