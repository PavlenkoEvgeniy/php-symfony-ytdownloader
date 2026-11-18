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

### Requirement: One active Telegram download at a time

Through the Telegram bot, a user SHALL have at most one Download Task in
`queued` or `processing` state. Sending a link while one is active SHALL
NOT probe or queue it: the bot SHALL answer that the current download must
be awaited or cancelled with /cancel. Once no task of the user is queued
or processing, the next link SHALL be accepted as usual.

#### Scenario: Second link while a download is active

- **WHEN** a Telegram user sends a link while one of their tasks is queued or processing
- **THEN** no new Download Task is queued
- **AND** the bot answers that the current download must be awaited or cancelled with /cancel

#### Scenario: Link after the active download ended

- **WHEN** none of a Telegram user's tasks are queued or processing
- **THEN** the next link is accepted as usual

#### Scenario: Cancelling frees the slot

- **WHEN** a Telegram user cancels their queued download with /cancel
- **THEN** their next link is accepted as usual

### Requirement: Telegram updates are acknowledged immediately

Telegram updates SHALL be received through the webhook or by polling the Bot
API server; both paths feed the same processing, the webhook confirms an
accepted update at once, and processing happens in the background. Repeated
arrival of one update through any combination of paths SHALL have its side
effects at most once. Polling SHALL resume after the newest known update;
an update that cannot be accepted SHALL be answered so Telegram retries.

#### Scenario: Acknowledgements do not wait for processing

- **WHEN** the webhook receives an update
- **THEN** it confirms the update to Telegram immediately
- **AND** the link checking and replies happen in the background

#### Scenario: Redelivered update is not repeated

- **WHEN** Telegram delivers an update that was already accepted
- **THEN** the messages and downloads of that update are not repeated

#### Scenario: Slow processing does not starve later updates

- **WHEN** the requester sends /cancel while another update is being processed
- **THEN** the cancellation is handled without waiting for that processing

#### Scenario: Update that cannot be accepted is retried

- **WHEN** the webhook cannot queue an update
- **THEN** it answers with an error so Telegram retries the delivery

#### Scenario: Polling keeps the bot working when webhook delivery stalls

- **WHEN** webhook deliveries stop arriving while the Bot API server still holds updates
- **THEN** the bot receives and answers updates through polling

#### Scenario: A polling restart does not replay the backlog

- **WHEN** polling starts after an outage and knows no previous position
- **THEN** it begins after the newest existing update
- **AND** the accumulated backlog is not replayed
