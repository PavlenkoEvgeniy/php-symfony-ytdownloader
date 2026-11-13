# Proposal

## Why

Two field-reported gaps after the Telegram size guard fires (2026-11-13):

1. Aborting a transfer leaves staged files behind — a 955 MB intermediate file
   was found in the downloads directory on production after a size rejection,
   even though the spec already requires that no file is kept.
2. The size-rejection message only *asks* the requester to choose another
   quality; it offers no quality selection, so the requester must send the
   link again and can re-pick a quality that is known to exceed the limit.

There is no GitHub issue for this work; the affected capability is
`download-recovery`.

## What Changes

- Every attempt stages its files in a private working area of the downloads
  directory; finished files are moved into the library, everything else is
  removed when the attempt ends, and leftovers of interrupted attempts are
  swept by later attempts.
- After the size-rejection message the bot sends a quality selection for the
  same link: qualities estimated above the configured upload limit and the
  rejected quality are left out, qualities with no usable estimate stay;
  when nothing remains the bot says so instead of showing an empty selection.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-recovery`: an ADDED requirement that failed/interrupted attempts
  leave no files in the downloads directory, and an ADDED requirement that a
  size rejection is followed by a filtered quality selection for the same
  link.

## Impact

- `YoutubeDlWrapper` (staging, relocation, stale sweep), new wrapper tests.
- `TelegramUpdateHandler` (+ interface) gains the quality-selection offer;
  `DownloadMessageHandler` calls it on the size-rejection path; en/ru
  translation keys for the selection prompt and the "nothing fits" answer.
- Disk usage of the downloads directory becomes predictable; resuming a
  half-downloaded transfer across retries is no longer possible (partials are
  discarded with the attempt).

Out of scope:

- Filtering qualities at the *initial* link intake (before the first pick) —
  it would add metadata probes to every link sent to the bot.
- Retry/resume of interrupted transfers.
- Automatic pruning of completed library files (an existing, separate
  concern).
