# Spec Delta

## ADDED Requirements

### Requirement: Delete from the Source details page

The Source details page SHALL offer a delete action that asks for confirmation
before deleting the file and its Source.

#### Scenario: Deleting from the details page

- **WHEN** a user clicks Delete on the Source details page
- **THEN** the user is asked to confirm
- **AND** after confirmation the file and its Source are deleted
- **AND** the user returns to the Library

#### Scenario: Cancelling the confirmation

- **WHEN** a user dismisses the confirmation
- **THEN** the Source is not deleted
