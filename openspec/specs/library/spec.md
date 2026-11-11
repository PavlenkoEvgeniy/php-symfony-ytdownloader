# library Specification

## Purpose
The Library is the set of Sources available to users for download. Retention and
pruning keep it within the configured storage budget.

## Requirements

### Requirement: Automatic orphan pruning

The service SHALL delete Orphan Files older than the configured TTL on its hourly
cleanup run, leaving Sources and files referenced by a Source untouched.

#### Scenario: Orphan older than the TTL

- **WHEN** the hourly cleanup runs and the downloads directory contains a file that no Source references and that is older than the TTL
- **THEN** the file is deleted

#### Scenario: Referenced file

- **WHEN** a file is referenced by a Source
- **THEN** the cleanup does not delete it

#### Scenario: Fresh file

- **WHEN** a file is younger than the TTL and may still be written by a worker
- **THEN** the cleanup does not delete it

### Requirement: File name opens the Source details

Clicking a file name in the Library SHALL open the details page of that Source.

#### Scenario: Clicking a file name

- **WHEN** a user clicks a file name in the Library
- **THEN** the details page of that Source opens

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

### Requirement: Readable filename validation message

Submitting a Source edit with a filename that contains path separators or is
a dot path SHALL be rejected with a human-readable explanation, never with a
raw translation key.

#### Scenario: Path traversal filename is rejected

- **WHEN** a user submits the Source edit form with a filename containing a
  path separator
- **THEN** the value is not saved
- **AND** the page shows a readable message explaining the filename rule
