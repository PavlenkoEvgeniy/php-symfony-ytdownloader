# Spec Delta

## ADDED Requirements

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
