# Design

## Context

All bot replies are string literals inside `TelegramUpdateHandler`; only
the delivery-failure and size-rejection notifications use the Symfony
translator (`telegram.*` keys, English and Russian). The web UI has its own
translations and stays untouched. Symfony translations already run with
`default_locale: en` and an English fallback, so an untranslated key in a
new locale file safely degrades to English.

## Goals / Non-Goals

**Goals:**
- A per-user language for every bot message, persisted across restarts.
- Ten languages, selectable from an inline keyboard with native names.
- Existing English behaviour byte-identical where it was already English.

**Non-Goals:**
- Web UI/admin localisation.
- Detecting the language from the Telegram profile (explicit choice only).

## Decisions

- **Locale on the user row** (`user.bot_locale`, nullable): the bot is a
  per-user surface, the value survives restarts via the database, and a
  linked user is required for the choice — an unlinked sender gets English.
- **All handler strings become `telegram.bot.*` keys** with the current
  English text as the `en` value verbatim, so existing behaviour and the
  existing test expectations do not shift.
- **Notifications resolve the locale by the chat's user**
  (`findOneByTelegramUserId`), because the failure and size-rejection
  messages are sent from the worker, not from the update handler.
- **The keyboard lists every language in its own script** (English,
  Русский, Español, …) — recognisable regardless of the current setting.
- **Invalid or unknown callback codes are rejected** against the fixed
  ten-language map; the stored column is length-limited to five chars.
- **Ten languages**: English, Russian, Spanish, Portuguese, German, French,
  Turkish, Arabic, Hindi, Chinese — a spread of the most widely used
  languages on Telegram and beyond.

## Risks / Trade-offs

- Translation quality outside English/Russian is best-effort; the meaning
  of every message is short and operational, and fallback keeps English as
  the reference.
- One more write per language change (a user row update) — negligible.
- The help text and menu now carry two more lines; tests that assert
  fragments keep passing because English values were preserved verbatim.
