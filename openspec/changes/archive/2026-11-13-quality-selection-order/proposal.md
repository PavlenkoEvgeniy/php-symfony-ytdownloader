# Proposal

## Why

Field report on `v1.15.4` (2026-11-13): after a `moderate` download was
rejected the offered keyboard still contained **Best**, because Best's own
estimate was missing or at the limit. Best can never be smaller than
Moderate, so offering it sends the requester straight back into the same
rejection. The qualities are ordered by size — Best > Moderate > Poor >
Audio — and the selection must respect that order.

There is no GitHub issue for this work; the affected capability is
`download-recovery`.

## What Changes

- A quality left out of the post-rejection selection (rejected, or
  estimated above the configured upload limit) also takes out every
  quality that produces a same-or-larger file — regardless of that
  quality's own estimate.
- The remaining qualities keep the order from the largest file to the
  smallest.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-recovery`: an ADDED requirement that the post-rejection quality
  selection respects the quality order.

## Impact

- `TelegramUpdateHandler::offeredQualities` computes a cut-off point in the
  ordered quality list instead of judging each quality independently; the
  qualities above the rejected one are no longer probed at all (one probe
  fewer than before).

Out of scope:

- The initial link intake keyboard (it offers every quality today and stays
  that way).
- Re-ranking or adding qualities; the order Best > Moderate > Poor > Audio
  is taken as a domain fact.
