# Spec Delta

## MODIFIED Requirements

### Requirement: Disk pressure guard

The service SHALL reject new Download Tasks on every surface while free space in
the download volume is below the configured minimum (5 GB by default), without
creating a task. An administrator SHALL be able to change the minimum at
runtime; a change SHALL apply to subsequent requests only, and a minimum of
0 SHALL disable the guard.

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

#### Scenario: Default minimum

- **WHEN** no administrator has changed the setting
- **THEN** the minimum is the deployment default of 5 GB

#### Scenario: Raising the minimum takes effect for subsequent requests

- **WHEN** an administrator raises the minimum above the current free space
- **THEN** a new download request on any surface is rejected without creating a task

#### Scenario: Lowering the minimum takes effect for subsequent requests

- **WHEN** an administrator lowers the minimum below the current free space
- **THEN** a new download request on any surface is accepted as usual

#### Scenario: Minimum of zero disables the guard

- **WHEN** the minimum is set to 0 and a user submits a download request
- **THEN** the request is accepted regardless of free space

#### Scenario: Threshold change does not affect accepted tasks

- **WHEN** an administrator changes the minimum while Download Tasks are queued or processing
- **THEN** those tasks continue through the normal lifecycle
