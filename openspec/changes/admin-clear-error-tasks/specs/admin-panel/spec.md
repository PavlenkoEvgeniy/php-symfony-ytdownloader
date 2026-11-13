# Spec Delta

## ADDED Requirements

### Requirement: Clearing error tasks from Active Tasks

The Admin Panel SHALL offer an action on the Active Tasks page that deletes
every Download Task in `error` status and leaves tasks in every other status
untouched. The action SHALL ask for confirmation, SHALL reject requests
without a valid CSRF token, and SHALL report the number of cleared tasks in
the admin flash and in the audit trail.

#### Scenario: Only error tasks are cleared

- **WHEN** an administrator clears the error list while Download Tasks exist in `error`, `queued` and `processing` statuses
- **THEN** every task in `error` status is deleted
- **AND** the `queued` and `processing` tasks are kept

#### Scenario: Confirmation is asked first

- **WHEN** an administrator triggers the clear action
- **THEN** the browser asks for confirmation before the request is sent
- **AND** cancelling the confirmation deletes nothing

#### Scenario: The cleared count is reported

- **WHEN** the clear action deletes one or more error tasks
- **THEN** the admin flash reports how many tasks were cleared
- **AND** the audit trail records the action with the cleared count

#### Scenario: Request without a valid CSRF token is rejected

- **WHEN** a clear request carries no valid CSRF token
- **THEN** no task is deleted
- **AND** the audit trail records the rejected attempt
