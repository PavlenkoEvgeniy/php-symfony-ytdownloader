# Proposal

## Why

The admin Users section shows a user's email, roles and enabled state, but
nothing about the Telegram Link. To find out whether a user can use the bot, an
administrator has to open the database or ask the user. The User already stores
the Telegram id and username, and the profile page already shows the status.

GitHub issue: #103.

## What Changes

- The admin user card (the Users detail page) shows whether the user is linked
  to Telegram: the linked account (`@username`, or the numeric id when the
  account has no username) or "Not linked".
- The field is read-only: the Telegram Link is created and removed by the bot
  and the profile, not by the admin panel.

## Capabilities

### New Capabilities

- `admin-panel`: the EasyAdmin section and the read-only information it shows
  about Users. This change introduces the capability with the Telegram Link
  status on the user card.

## Impact

- The admin Users CRUD and translations; no entity or database change.
- Out of scope: an admin action to unlink or edit the Telegram Link, a Telegram
  column or filter in the Users list, and the bot/profile behavior itself.
