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
