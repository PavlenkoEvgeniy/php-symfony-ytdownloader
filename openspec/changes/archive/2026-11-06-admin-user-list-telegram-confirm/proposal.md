# Proposal

## Why

The Users list in the Admin Panel does not show whether a User is linked to the
Telegram bot, so the administrator has to open every user card to find out. The
Enabled toggle in the same list applies immediately on click, with no
confirmation, so an accidental click can disable a user.

## What Changes

- The Users list shows a read-only Telegram column per row: Yes when the User has
  a Telegram Link, No otherwise.
- Toggling the Enabled switch in the Users list asks for confirmation first.
  Cancelling leaves the User's status unchanged.
- Toggling the web download intake switch on the Settings page asks for
  confirmation first. Cancelling leaves the switch unchanged.
- The web intake switch is aligned with the surrounding text instead of being
  offset to the left.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `admin-panel`: add the Telegram link status to the Users list and require a
  confirmation before the inline Enabled toggle changes a User or the Settings
  page changes the web download intake.

## Impact

- `UserCrudController`, the EasyAdmin boolean field template override,
  translations and controller tests.
- Out of scope: changing the Telegram Link status on the user card (already
  specified), adding a confirmation to other boolean fields, and disabling the
  Telegram link from the Users list.
