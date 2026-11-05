# Design

## Context

`User` stores `telegramUserId`, `telegramUsername` and `telegramLinkedAt`;
`isTelegramLinked()` reports whether the link exists. The user-facing profile
already renders the link as `@username`, falling back to the numeric id. The
admin Users CRUD configures its fields explicitly, so none of these values are
shown today.

## Goals / Non-Goals

**Goals:**

- Show the Telegram Link status on the admin user card.
- Use the same wording the user sees in the profile.

**Non-Goals:**

- Editing or removing the Telegram Link from the admin panel.
- Adding a column, filter or search field to the Users list.
- Changing how the bot or the profile manages the link.

## Decisions

- **One read-only field on the detail page.** The field is bound to the existing
  `telegramUsername` property and formats both linked and unlinked states, so the
  entity does not gain a display-only getter. It is shown only on the detail page:
  the issue asks for the user card, and the list stays compact.
- **Reuse the profile wording.** A linked user is shown as `@username`, or as the
  numeric Telegram id when no username is set; an unlinked user is shown as "Not
  linked". This is the same rule the profile already follows.
- **Read-only.** The Telegram Link belongs to the bot (`/login`, `/logout`) and
  the profile; an admin editor would create a second, unaudited way to change the
  association.
- **No entity or migration change.** All required values and the `isTelegramLinked()`
  check already exist.

## Risks / Trade-offs

- An administrator cannot revoke a link from the card; that remains a user action
  in the profile. A forced unlink would be a separate change with its own audit
  events.
- The status is a snapshot rendered on page load; no caching is involved.
