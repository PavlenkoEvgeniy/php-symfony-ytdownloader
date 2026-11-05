# Spec Delta

## ADDED Requirements

### Requirement: File row opens the Source details

Clicking anywhere in a file row in the Library SHALL open the details page of
that Source, while the row actions keep working as separate controls.

#### Scenario: Clicking the file row

- **WHEN** a user clicks anywhere in a file row outside its action buttons
- **THEN** the details page of that Source opens

#### Scenario: Using a row action

- **WHEN** a user clicks Download, Edit or Delete in a file row
- **THEN** that action runs
- **AND** the details page does not open
