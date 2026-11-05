# Spec Delta

## ADDED Requirements

### Requirement: Audit outcome is shown as text

The admin Audit Event list and card SHALL show whether the action succeeded as
Yes or No text, not as a switch.

#### Scenario: Successful event

- **WHEN** an administrator views an Audit Event whose action succeeded
- **THEN** the outcome is shown as Yes

#### Scenario: Failed event

- **WHEN** an administrator views an Audit Event whose action failed
- **THEN** the outcome is shown as No
