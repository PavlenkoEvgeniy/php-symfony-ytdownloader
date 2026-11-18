# Spec Delta

## ADDED Requirements

### Requirement: Telegram updates are acknowledged immediately

The bot webhook SHALL confirm every accepted update to Telegram at once and
process it in the background; the duration of link probing, replying or
queueing SHALL NOT delay that confirmation. When Telegram delivers the same
update more than once, its side effects SHALL happen at most once. An update
that cannot be accepted SHALL be answered so that Telegram retries it.

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
