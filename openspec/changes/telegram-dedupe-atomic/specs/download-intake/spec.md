# Spec Delta

## MODIFIED Requirements

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
