# Proposal

## Why

Field report (2026-11-13): an Audio download sat in `processing` with
nothing happening — the transfer stalled and the process timeout is six
hours, so the task would stay "processing" essentially forever and the
requester never learns anything.

The diagnosis exposed a second, older problem: the downloader is started in
quiet mode, which suppresses **all** progress output — measured on
production arguments, a running transfer produces zero bytes of output.
That is why the requester sees no changes at all, and why the periodic
Telegram progress ticks specified in `download-progress` never fire.

There is no GitHub issue for this work; the affected capability is
`download-recovery`.

## What Changes

- A stall watchdog in the download attempt: if nothing observable changes
  for one minute from the start of the attempt — no downloader output and
  no change to the files being written — the attempt is aborted, the task
  ends in `error` with a stored reason, no automatic retry is scheduled and
  the requester is notified with the existing generic failure message.
- The downloader is started with its progress bar enabled, restoring the
  progress output the wrapper already parses (an already-specified
  `download-progress` behavior; no spec delta for it).

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-recovery`: an ADDED requirement that a stalled attempt fails
  fast with the generic failure notification.

## Impact

- `YoutubeDlWrapper`: polling loop with two activity signals (output
  arrival, staging-directory changes) and a one-minute stall timeout;
  `--progress` added to the downloader arguments.
- Failure classification: the stall reason matches no transient pattern and
  is therefore permanent — the message goes out after the first stalled
  minute instead of after retries.
- Telegram progress ticks start working again (spec restoration).

Out of scope:

- Shortening or making configurable the six-hour process timeout.
- Detecting a worker process that dies without running code (already
  covered by the stuck-task reset at container start and in the admin).
