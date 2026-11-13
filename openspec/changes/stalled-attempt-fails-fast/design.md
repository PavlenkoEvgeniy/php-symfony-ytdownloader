# Design

## Context

The wrapper ran the downloader with `foreach ($process ...)`, which yields
only on output — and quiet mode (`--print` implies it) guarantees there is
none during a transfer: measured with the production arguments, a running
download produced zero bytes, with and without `--progress` considered
separately later. The process timeout (`yt_dlp.timeout`) is 21600 s, so a
stalled transfer parks the task in `processing` for up to six hours.
Progress output exists in yt-dlp behind the `--progress` flag even in quiet
mode (verified: `PROGRESS:  0.0%` lines, ~1.4/s during an active
rate-limited transfer, parseable by the wrapper's existing pattern).

## Goals / Non-Goals

**Goals:**
- A transfer that stops changing fails within a minute, visibly, with the
  existing failure message and Re-download action.
- Healthy downloads — including long silent phases like audio conversion —
  are never aborted.
- The requester can again see periodic progress (already specified
  behavior, currently dead).

**Non-Goals:**
- Shortening the six-hour process timeout or the retry delays.
- Notifying web/API requesters differently (they have no chat; the audit
  entry and task status carry the outcome).

## Decisions

- **Two activity signals, either resets the window**: (a) any bytes
  arriving from the process (progress lines once `--progress` is on,
  errors any time), (b) a signature of the staging directory — path, size
  and mtime of every file. Signal (b) alone covers the audio-conversion
  phase, where no progress lines are emitted for potentially minutes while
  ffmpeg keeps writing its output; signal (a) alone would miss a stalled
  transfer whose last progress line already arrived.
- **Polling loop instead of `foreach`**: the Symfony process iterator
  yields only on output, so with a silent process the loop body never runs
  and no watchdog code can execute. The loop polls incremental output and
  `isRunning()` every 200 ms and calls `checkTimeout()` itself, keeping the
  existing timeout behavior and message.
- **One minute, fixed**, as requested; measured from the last observable
  change (initialised at process start), so the first window covers the
  metadata phase too. A metadata fetch slower than a minute is accepted as
  a stall.
- **Stall reason is permanent**: the message ("stalled", no transient
  substring) matches no transient pattern, so the classifier returns
  permanent — the requester is notified after the first stalled minute
  rather than after three more attempts; Re-download remains the manual
  retry path.
- **`--progress` restored** rather than inventing a second progress
  channel: the wrapper already parses `PROGRESS:` lines; the flag only
  undoes the quiet-mode suppression of the progress bar. It does not
  affect refusal detection (the refusal line stays suppressed; the
  outcome-based recognition from the quiet-mode fix is untouched).

## Risks / Trade-offs

- A genuinely slow metadata phase (over a minute before any file or
  output) is killed as a stall; accepted — the requester gets a fast
  failure with Re-download instead of a silent hang.
- Progress lines arrive per percent change (~1/s measured), so output
  activity and file activity overlap during transfer; the snapshot scan
  runs over a per-run directory with a handful of files and is cheap at
  five polls per second.
- The staging signature treats a same-size same-mtime rewrite as no
  change; ext4 timestamps are nanosecond-resolution and sizes grow during
  transfer, so the practical false-negative window is nil.
