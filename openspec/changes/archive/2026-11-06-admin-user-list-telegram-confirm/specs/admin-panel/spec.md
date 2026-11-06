# Spec Delta

## ADDED Requirements

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
