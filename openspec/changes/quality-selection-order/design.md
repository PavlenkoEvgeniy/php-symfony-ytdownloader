# Design

## Context

`offeredQualities` judged each quality on its own estimate: a quality with
no usable estimate was offered unconditionally. The field case showed the
gap — the rejected `moderate`'s *larger* neighbour `best` had no blocking
estimate and reappeared on the keyboard. Sizes of the four qualities for one
link are monotonic (Best ≥ Moderate ≥ Poor ≥ Audio), so a per-quality
judgement misses cut decisions that propagate upwards.

## Goals / Non-Goals

**Goals:**
- A cut (rejected or estimated over the limit) never leaves a same-or-larger
  quality on the keyboard.
- Keep the offered list in the fixed quality order.

**Non-Goals:**
- Changing the initial intake keyboard or the quality set itself.
- Persisting estimates between the intake probe and the rejection offer.

## Decisions

- **Single cut-off index instead of per-quality flags.** The ordered
  quality list gets one index: the maximum of the rejected quality's index
  and the indices of every quality estimated over the limit; everything at
  or below that index is left out, everything above it is offered. A
  per-quality flag would keep exactly the observed bug (a larger quality
  with an unknown estimate survives).
- **No probe above the cut-off.** Only qualities after the rejected index
  are estimated: a larger quality is cut regardless of its own estimate, so
  probing it would spend a metadata request (up to 20 s) on a decision that
  the order already fixes. That is one probe fewer than the previous
  implementation.
- **Unknown rejected quality degrades gracefully**: without a known index
  only the estimates cut, and the rejected quality name itself is simply
  not in the list.

## Risks / Trade-offs

- The order Best > Moderate > Poor > Audio is assumed, not derived from
  actual stream sizes; a pathological link where "poor" turns out larger
  than "moderate" would be judged by the order, not by bytes. Accepted:
  the domain states the order, and the transfer guard still enforces the
  hard limit.
