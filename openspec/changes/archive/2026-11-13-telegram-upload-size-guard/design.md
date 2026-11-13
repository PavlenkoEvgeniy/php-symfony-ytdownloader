# Design

## Context

See proposal.md for motivation. Relevant current state:

- `VideoDownloaderService::process()` resolves the download format via
  `FormatResolver` and then runs the downloader; failures come back as a
  `DownloadResult` carrying a reason and a transient/permanent kind.
- `DownloadMessageHandler` marks a permanent failure as `error` and, for
  Telegram-origin tasks, calls `TelegramNotifier::notifyDownloadFailure()`,
  which sends the fixed generic text (ADR-0017) plus a Re-download button and
  the library link.
- `TelegramDeliveryMessageHandler` is the backstop: after the transfer it
  refuses files over `TELEGRAM_MAX_UPLOAD_MB` and logs a warning.
- Metadata probing already exists (`YoutubeDlProbe` uses the yt-dlp wrapper
  client's `extractInfo` for playlist detection), so a second probe for sizes
  follows an established pattern.
- Relevant ADRs: ADR-0008 (local Bot API raises the limit to 2000 MB),
  ADR-0017 (generic failure message), ADR-0014 (attempt history), ADR-0010
  (disk-pressure guard at intake).

## Goals / Non-Goals

**Goals:**

- Reject an over-limit Telegram download before any transfer happens.
- Give the requester a message they can act on (pick another quality).
- Keep the stored reason visible to administrators, as in every other failure.

**Non-Goals:**

- Restricting web/UI/API downloads (no upload limit exists there).
- Changing the delivery-time size check (it stays as the backstop).
- Estimating playlist totals as a whole: the guard is judged per file, because
  delivery and the upload limit are per file.
- Reworking ADR-0017 beyond the single carve-out for the size rejection.

## Decisions

1. **Probe at processing time, not at intake.** The check runs in
   `VideoDownloaderService` after `FormatResolver` resolves the quality and
   before the download process starts. Intake (the bot's quality confirmation)
   would give an even earlier answer, but it would double the metadata requests
   for every link regardless of quality, and the resolved quality is what the
   estimate must be computed for anyway. Alternative considered: rejecting at
   intake — rejected because the estimate depends on the chosen quality and the
   probe would then be repeated at processing time regardless.

2. **A dedicated estimate service, not a branch inside the wrapper.** A small
   `DownloadSizeEstimator`-style service runs the same yt-dlp metadata call
   (`extractInfo`, non-flat, with the resolved format selector) and sums
   `filesize` (exact) or `filesize_approx` (fallback) over the formats the
   selector picks, per playlist entry. Rationale: `YoutubeDlWrapper` observes a
   running process, while estimation is a metadata concern; `YoutubeDlProbe`
   already proves the wrapper client fits this. Alternative considered:
   yt-dlp's built-in `filesize<?2000M` format filter — rejected: it filters
   each stream separately rather than the merged result, and its failure is a
   technical error string, not an actionable outcome (see proposal).

3. **The rejection is a distinct outcome of `DownloadResult`.** The result
   carries a machine-checkable marker (e.g. a dedicated failure kind or flag)
   so `DownloadMessageHandler` can choose the notification; the human reason
   still lands on the task via the existing `markError` path. Alternative
   considered: matching on the reason string in the notifier — rejected: ADR-0017
   deliberately removed the reason from the message layer, and string matching
   is brittle.

4. **A dedicated notifier method with its own translation key**
   (`telegram.file_too_large`, en + ru): names the size problem, asks for
   another quality, carries no Re-download button. This is a deliberate
   exception to ADR-0017 — its rationale was that downloader internals are noise,
   while this message is the one case where the requester needs the reason to
   act. Record the exception as a short follow-up note in ADR-0017 rather than
   a new ADR.

5. **Permanent failure, no retry.** A size rejection cannot be cured by another
   attempt at the same quality, so it maps to the existing permanent kind and
   never enters the retry scheduling path.

6. **Fail open on uncertainty.** If the probe errors or yields no usable size,
   the download proceeds; the delivery-time check still protects Telegram.
   Rationale: a probe outage must not break downloads that used to work.

7. **Limit = `TELEGRAM_MAX_UPLOAD_MB`, no new setting.** The guard compares
   against the same value the delivery backstop uses, so intake behavior and
   delivery behavior cannot disagree.

## Risks / Trade-offs

- [`filesize_approx` is an estimate; the real file may land slightly over the
  limit] → the delivery-time check remains the hard guarantee; the pre-check is
  best-effort and errs toward downloading when unsure.
- [Every Telegram download now costs an extra metadata request before the
  transfer] → the probe reuses the existing wrapper client with a short
  socket timeout; it replaces nothing and adds sub-second overhead for typical
  sources.
- [A probe that hangs would stall the worker] → the probe carries its own
  bounded timeout; on timeout it counts as "no estimate" (fail open).
- [The new message diverges from ADR-0017] → recorded as an explicit exception
  in the ADR follow-up so future readers do not "fix" it back to generic.
