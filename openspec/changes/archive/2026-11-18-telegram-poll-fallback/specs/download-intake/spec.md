# Spec Delta

## MODIFIED Requirements

### Requirement: Telegram updates are acknowledged immediately

Telegram updates SHALL be received through the webhook or by polling the Bot
API server; both paths SHALL feed the same processing, and the webhook SHALL
confirm an accepted update to Telegram at once while processing happens in
the background. When the same update arrives more than once through any
combination of paths, its side effects SHALL happen at most once. Polling
SHALL resume after the newest known update, and an update that cannot be
accepted SHALL be answered so that Telegram retries it.

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
