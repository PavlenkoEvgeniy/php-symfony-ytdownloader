# Spec Delta

## ADDED Requirements

### Requirement: Long audit values stay inside the mobile card

On a narrow screen the admin Audit Event list SHALL wrap long unbroken values
inside their card instead of letting them overflow the card.

#### Scenario: Long target URL on a phone

- **WHEN** an administrator opens the Audit Event list on a narrow screen
- **AND** an event has a long unbroken target or actor value
- **THEN** the value wraps within the card
- **AND** it does not extend beyond the card border
