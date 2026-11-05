# admin-panel Specification

## Purpose
The Admin Panel is the EasyAdmin section at `/admin`, where an administrator
manages Users, Sources, Logs, Audit Events and settings. It shows operational
state but does not replace the user-facing profile for actions owned by the user.

## Requirements

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

### Requirement: Audit outcome is shown as text

The admin Audit Event list and card SHALL show whether the action succeeded as
Yes or No text, not as a switch.

#### Scenario: Successful event

- **WHEN** an administrator views an Audit Event whose action succeeded
- **THEN** the outcome is shown as Yes

#### Scenario: Failed event

- **WHEN** an administrator views an Audit Event whose action failed
- **THEN** the outcome is shown as No

### Requirement: Opening a user shows the user card

The admin Users list SHALL open the user card (detail view) when an administrator
clicks a user, not the edit form.

#### Scenario: Clicking a user row

- **WHEN** an administrator clicks a user in the Users list
- **THEN** the user card opens
- **AND** the edit form is not opened

### Requirement: Long audit values stay inside the mobile card

On a narrow screen the admin Audit Event list SHALL wrap long unbroken values
inside their card instead of letting them overflow the card.

#### Scenario: Long target URL on a phone

- **WHEN** an administrator opens the Audit Event list on a narrow screen
- **AND** an event has a long unbroken target or actor value
- **THEN** the value wraps within the card
- **AND** it does not extend beyond the card border
