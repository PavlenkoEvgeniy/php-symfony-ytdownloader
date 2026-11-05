# download-intake Specification

## Purpose
Define how the service accepts new Download Tasks from its surfaces: the web UI,
the REST API v1/v2 and the Telegram bot, including the admin switch that can
close the web surfaces.

## Requirements

### Requirement: Web download intake switch

The service SHALL let an administrator disable new Download Tasks from the web
surfaces — the UI and REST API v1/v2 — at runtime. The switch SHALL be enabled by
default.

#### Scenario: Default enabled

- **WHEN** no administrator has changed the setting
- **THEN** the UI and both REST APIs accept new Download Tasks

#### Scenario: Administrator disables the web intake

- **WHEN** an administrator turns the web download intake off
- **THEN** the change is recorded in the audit log
- **AND** the UI and both REST APIs reject new Download Tasks

### Requirement: Rejection on the web surfaces

The service SHALL reject a new Download Task on a disabled web surface without
creating a task.

#### Scenario: UI request while disabled

- **WHEN** a user opens the download page or submits its form while the web intake is off
- **THEN** no Download Task is created
- **AND** the user is led to the Library with a message that web downloads are disabled

#### Scenario: API request while disabled

- **WHEN** a client calls the create endpoint of REST API v1 or v2 while the web intake is off
- **THEN** the service responds with 403 Problem Details
- **AND** no Download Task is created

### Requirement: Telegram surface is independent

The Telegram bot SHALL keep accepting new Download Tasks while the web intake is
disabled.

#### Scenario: Telegram request while disabled

- **WHEN** a linked user sends a video link to the bot while the web intake is off
- **THEN** the bot creates a Download Task as usual

### Requirement: Already accepted tasks are not revoked

Disabling the web intake SHALL NOT cancel or stop Download Tasks that were
already accepted.

#### Scenario: Tasks queued before the switch

- **WHEN** the web intake is disabled while Download Tasks are queued or processing
- **THEN** those tasks continue through the normal lifecycle

### Requirement: Library remains available

Disabling the web intake SHALL NOT restrict access to the Library.

#### Scenario: Library while disabled

- **WHEN** the web intake is disabled
- **THEN** users can still list Sources and download their files
