# Spec Delta

## ADDED Requirements

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
