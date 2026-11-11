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

### Requirement: Telegram Link status in the Users list

The admin Users list SHALL show whether each User is linked to Telegram: Yes for
a User with a Telegram Link, No otherwise. The value SHALL be read-only.

#### Scenario: Linked user in the list

- **WHEN** an administrator opens the Users list
- **THEN** the row of a User with a Telegram Link shows Yes in the Telegram column

#### Scenario: Unlinked user in the list

- **WHEN** an administrator opens the Users list
- **THEN** the row of a User without a Telegram Link shows No in the Telegram column

#### Scenario: Read-only list value

- **WHEN** an administrator views the Telegram column in the Users list
- **THEN** it has no control that changes the link

### Requirement: Confirmation before changing the enabled status

Changing the Enabled switch of a User in the Users list SHALL ask the
administrator for confirmation first.

#### Scenario: Administrator confirms the change

- **WHEN** an administrator toggles Enabled for a User in the Users list
- **THEN** the browser asks for confirmation
- **AND** the new status is saved only after confirmation

#### Scenario: Administrator cancels the change

- **WHEN** an administrator cancels the confirmation
- **THEN** the switch returns to its previous position
- **AND** the User's status is not changed

### Requirement: Confirmation before changing the web download intake

Toggling the web download intake switch on the Settings page SHALL ask the
administrator for confirmation first.

#### Scenario: Administrator confirms the intake change

- **WHEN** an administrator toggles the web download intake switch
- **THEN** the browser asks for confirmation
- **AND** the switch changes only after confirmation

#### Scenario: Administrator cancels the intake change

- **WHEN** an administrator cancels the confirmation
- **THEN** the switch stays in its previous position

### Requirement: Disk state on the Settings page

The Admin Panel Settings page SHALL show the current free space in the download
volume and whether it is below the effective minimum. It SHALL show the
effective minimum with its origin — the deployment default or an
administrator-set value — and accept the threshold as a non-negative whole
number of megabytes up to a supported maximum of 1048576, where an empty
value follows the deployment default.

#### Scenario: Effective minimum from the deployment default

- **WHEN** an administrator opens the Settings page before ever saving a threshold
- **THEN** the threshold field is empty
- **AND** the page shows the deployment default as the effective minimum

#### Scenario: Administrator sets the threshold

- **WHEN** an administrator saves a positive threshold in megabytes
- **THEN** the page shows that value as the administrator-set effective minimum

#### Scenario: Clearing the field returns to the deployment default

- **WHEN** an administrator clears the threshold field and saves
- **THEN** the page shows the deployment default as the effective minimum again

#### Scenario: Negative value is rejected

- **WHEN** an administrator tries to save a negative threshold
- **THEN** the value is not saved
- **AND** the page reports that the threshold must be zero or greater

#### Scenario: Value above the maximum is rejected

- **WHEN** an administrator tries to save a threshold above 1048576 megabytes
- **THEN** the value is not saved
- **AND** the page reports the supported maximum

#### Scenario: Free space below the effective minimum

- **WHEN** an administrator opens the Settings page while free space in the download volume is below the effective minimum
- **THEN** the page shows that the free space is below the minimum
