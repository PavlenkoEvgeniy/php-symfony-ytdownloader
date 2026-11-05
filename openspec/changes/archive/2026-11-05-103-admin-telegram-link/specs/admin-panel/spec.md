# Spec Delta

## Purpose

The Admin Panel is the EasyAdmin section at `/admin`, where an administrator
manages Users, Sources, Logs, Audit Events and settings. It shows operational
state but does not replace the user-facing profile for actions owned by the user.

## ADDED Requirements

### Requirement: Telegram Link status on the user card

The admin user card SHALL show whether the User is linked to Telegram. A linked
User SHALL be shown with the linked Telegram account; an unlinked User SHALL be
shown as not linked. The information SHALL be read-only.

#### Scenario: Linked user with a username

- **WHEN** an administrator opens the card of a User who has a Telegram Link with a username
- **THEN** the card shows that the User is linked and displays the username

#### Scenario: Linked user without a username

- **WHEN** an administrator opens the card of a User whose linked Telegram account has no username
- **THEN** the card shows that the User is linked and displays the Telegram id

#### Scenario: Unlinked user

- **WHEN** an administrator opens the card of a User without a Telegram Link
- **THEN** the card shows that the User is not linked

#### Scenario: Read-only status

- **WHEN** an administrator edits a User
- **THEN** the Telegram Link status is not an editable form field
