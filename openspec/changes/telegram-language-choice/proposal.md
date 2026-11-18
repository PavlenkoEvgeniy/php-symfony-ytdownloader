# Proposal

## Why

Request (2026-11-18): the bot must speak the user's language. Today every
Telegram message is hardcoded English (only the failure and size-rejection
notifications go through the translator). The requester wants `/language`
with a choice among the ten most widely used languages, and the bot's
messages to follow that choice.

There is no GitHub issue for this work; the affected capability is
`download-intake` (the Telegram surface capability).

## What Changes

- `/language` command: an inline keyboard listing ten languages (each in
  its own script), the choice saved per linked user.
- Every bot message moves to translation keys; the bot replies in the
  chosen language, English as the default and the fallback.
- The command is registered in the command menu and listed in `/help`.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-intake`: an ADDED requirement that the bot speaks the user's
  chosen language and that `/language` offers the supported languages.

## Impact

- `User` gains a nullable `bot_locale` column (migration); the setting is
  per linked user.
- `TelegramUpdateHandler` passes every user-facing string through the
  translator with the user's locale; failure and size-rejection
  notifications resolve the locale from the chat's user.
- Ten locale files ship: English, Russian, Spanish, Portuguese, German,
  French, Turkish, Arabic, Hindi and Chinese (missing keys fall back to
  English).

Out of scope:

- Localising the web UI or admin (Twig) — the request is about bot
  messages.
- RTL layout concerns for Arabic beyond the message text itself.
