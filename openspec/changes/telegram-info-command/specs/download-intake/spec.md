# Spec Delta

## ADDED Requirements

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
