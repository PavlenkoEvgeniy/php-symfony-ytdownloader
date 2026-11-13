# Spec Delta

## ADDED Requirements

### Requirement: Larger qualities are not offered after a rejection

The offered qualities are ordered from the largest file to the smallest
(Best, Moderate, Poor, Audio). When a quality is left out of the selection
because it was rejected or estimated above the configured upload limit,
every quality that produces a same-or-larger file SHALL be left out as
well, even when its own estimate is missing or at or below the limit.

#### Scenario: A larger quality is not offered after a rejection

- **WHEN** a quality was rejected and a same-or-larger quality has no usable estimate or is estimated at or below the limit
- **THEN** the same-or-larger quality is not offered

#### Scenario: Larger qualities follow an over-limit quality

- **WHEN** a quality is estimated above the configured upload limit
- **THEN** every quality that produces a same-or-larger file is not offered

#### Scenario: Remaining qualities keep the size order

- **WHEN** a selection is offered after a rejection
- **THEN** the remaining qualities keep the order from the largest file to the smallest
