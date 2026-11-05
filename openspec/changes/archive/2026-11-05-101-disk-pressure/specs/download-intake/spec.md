# Spec Delta

## ADDED Requirements

### Requirement: Disk pressure guard

The service SHALL reject new Download Tasks on every surface while free space in
the download volume is below the configured minimum (5 GB by default), without
creating a task.

#### Scenario: UI request under disk pressure

- **WHEN** a user submits the download form while free space is below the minimum
- **THEN** no Download Task is created
- **AND** the user sees a message that there is not enough free disk space

#### Scenario: API request under disk pressure

- **WHEN** a client calls the create endpoint of REST API v1 or v2 while free space is below the minimum
- **THEN** the service responds with 507 Problem Details
- **AND** no Download Task is created

#### Scenario: Telegram request under disk pressure

- **WHEN** a linked user confirms a download in the bot while free space is below the minimum
- **THEN** the bot replies that there is not enough free disk space
- **AND** no Download Task is created
