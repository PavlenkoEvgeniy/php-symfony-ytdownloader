# Proposal

## Why

On a phone the Audit Event list turns each row into a card and squeezes the
value into the right 65% of the card. Long unbroken values such as video URLs
do not wrap and overflow the card, so the page becomes unreadable.

GitHub issue: #108.

## What Changes

- Long values in the Audit Event list wrap inside the mobile card instead of
  overflowing it.
- The desktop table is unchanged.

## Capabilities

### Modified Capabilities

- `admin-panel`: audit values stay inside their card on narrow screens.

## Impact

- One admin stylesheet loaded by the Audit Event CRUD; no data change.
- Out of scope: redesigning the other admin lists for mobile.
