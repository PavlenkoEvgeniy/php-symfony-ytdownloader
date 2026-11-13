# Spec Delta

## ADDED Requirements

### Requirement: A finished attempt leaves no staged files

When an attempt ends without a usable file — it fails, times out, or is
aborted by the size guard — every file it produced SHALL be removed and
SHALL NOT remain in the downloads directory. Leftovers of an attempt that
was interrupted before it could clean up SHALL be removed by a later
attempt. A successful attempt SHALL keep its produced file.

#### Scenario: Size-guard abort leaves no files

- **WHEN** a transfer is aborted by the size guard
- **THEN** no file from that attempt remains in the downloads directory

#### Scenario: Failed attempt leaves no files

- **WHEN** an attempt fails or times out
- **THEN** no file from that attempt remains in the downloads directory

#### Scenario: Interrupted attempt is cleaned up later

- **WHEN** an attempt was interrupted before it could remove its files
- **THEN** a later attempt removes the leftovers of the interrupted attempt

#### Scenario: Successful attempt keeps its file

- **WHEN** an attempt succeeds
- **THEN** its produced file remains in the downloads directory as library content

### Requirement: Quality selection after a size rejection

After the size-rejection message, the bot SHALL offer the quality selection
for the same link. Qualities estimated above the configured upload limit
SHALL be left out, qualities without a usable estimate SHALL still be
offered, and the rejected quality SHALL NOT be offered again. When no
quality remains, the bot SHALL say so instead of showing an empty
selection. Picking a quality SHALL queue a fresh download for the same link.

#### Scenario: Quality selection follows the rejection

- **WHEN** a Telegram-origin task is rejected by the size guard
- **THEN** the bot offers a quality selection for the same link after the rejection message

#### Scenario: Picking an offered quality queues a download

- **WHEN** the requester picks a quality from the offered selection
- **THEN** a new Download Task is queued for the same link

#### Scenario: Qualities over the limit are not offered

- **WHEN** another quality is estimated above the configured upload limit
- **THEN** that quality is not offered

#### Scenario: Unknown estimates are still offered

- **WHEN** a quality has no usable size estimate
- **THEN** that quality is still offered

#### Scenario: The rejected quality is not offered again

- **WHEN** the selection follows the rejection of one quality
- **THEN** that rejected quality is not part of the selection

#### Scenario: No quality remains

- **WHEN** every other quality is estimated above the configured upload limit
- **THEN** the bot states that no other quality fits instead of showing an empty selection
