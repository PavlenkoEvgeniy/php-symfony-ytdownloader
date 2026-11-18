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

- **WHEN** the same update arrives again, through the same path or another one, before or after handling
- **THEN** its messages and downloads are not repeated
- **AND** the claim succeeds for exactly one arrival

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

### Requirement: A message flood cannot reach the requester

The Telegram bot SHALL send at most ten messages to one chat within a
minute, counting text messages, media files and message edits together.
Messages produced beyond that ceiling SHALL be dropped and recorded for
administrators; they SHALL NOT surface as errors to the requester and
SHALL NOT change how updates or downloads are processed.

#### Scenario: Traffic within the ceiling is delivered

- **WHEN** the bot sends up to ten messages to one chat within a minute
- **THEN** all of them are delivered

#### Scenario: Excess messages are dropped and recorded

- **WHEN** more than ten messages are produced for one chat within a minute
- **THEN** the excess messages are not sent
- **AND** every drop is recorded for administrators

#### Scenario: Downloads are not affected by the ceiling

- **WHEN** messages are dropped by the ceiling
- **THEN** update processing and download tasks continue as usual

### Requirement: The bot reports its version and last update

The bot SHALL answer /info with the running application version and the
time of the last deployment in UTC, and SHALL list /info in the command
menu and in the /help answer.

#### Scenario: Info shows version and update time

- **WHEN** a Telegram user sends /info
- **THEN** the bot replies with the application version
- **AND** the reply states the last deployment time in UTC

#### Scenario: Info is discoverable

- **WHEN** the command menu is registered or a user sends /help
- **THEN** /info is listed

#### Scenario: Update time is unknown

- **WHEN** the deployment time cannot be read
- **THEN** the reply says it is unknown instead of failing

### Requirement: The bot speaks the user's language

A linked user SHALL choose the language of bot messages with /language,
from ten languages: English, Russian, Spanish, Portuguese, German, French,
Turkish, Arabic, Hindi and Chinese. The bot SHALL answer in that language
from then on, English being the default and the fallback for anything not
translated. The choice SHALL survive restarts, and /language SHALL be
listed in the command menu.

#### Scenario: Choosing a language

- **WHEN** a linked user sends /language and picks a language
- **THEN** the choice is remembered for the user
- **AND** the reply confirming the choice is written in that language

#### Scenario: Later messages follow the choice

- **WHEN** a user who chose a language receives any bot message afterwards
- **THEN** the message is written in that language

#### Scenario: Default and fallback stay English

- **WHEN** a user never chose a language, or a message is not translated
- **THEN** the bot answers in English

#### Scenario: Language command is discoverable

- **WHEN** the command menu is registered or a user sends /help
- **THEN** /language is listed
