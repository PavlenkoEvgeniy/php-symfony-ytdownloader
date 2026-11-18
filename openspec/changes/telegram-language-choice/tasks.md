# Tasks

## 1. Per-user language

- [x] 1.1 Entity + migration: nullable `bot_locale` on the user row.
- [x] 1.2 Move every handler string to `telegram.bot.*` keys (English
      values verbatim), route replies through the translator with the
      user's locale; notifications resolve the locale from the chat's user.
- [x] 1.3 `/language` command with a ten-language inline keyboard, the
      callback saves the choice and confirms in that language; register the
      command in the menu and `/help`.
- [x] 1.4 Locale files: complete English and Russian bot sets plus Spanish,
      Portuguese, German, French, Turkish, Arabic, Hindi and Chinese (bot
      keys and notification keys; everything else falls back to English).
- [x] 1.5 Tests: keyboard lists ten languages; choosing saves the locale
      and confirms; later messages follow the locale (asserted through the
      translator, not hardcoded scripts); notifier respects the locale.

## 2. Integration checks

- [x] 2.1 Full test suite passes (`make test`).
- [x] 2.2 Lint passes (`make lint`: php-cs-fixer, PHPStan 6, Psalm 4, peck).
- [x] 2.3 OpenSpec validation passes (`make openspec-validate`).

## Workflow follow-up

- Commit, push, open a PR (together with the /info change), merge it, and
  cut the release tag.
- Archive both changes (`/opsx:archive`) after the release.
- Update the task journal in `$TASKS_DIR/ytdownloader/` (pull --rebase
  first, commit and push).
