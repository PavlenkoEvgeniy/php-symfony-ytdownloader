# Design

## Context

The metadata estimate shipped in `telegram-upload-size-guard` only works when
the extractor publishes sizes. VK Video publishes none (0 of 34 formats carry
`filesize`/`filesize_approx`, top-level fields null), so the guard failed open
and a 3.64 GB file completed and was refused only at delivery. The limit itself
comes from the local Bot API server (ADR-0008) via `TELEGRAM_MAX_UPLOAD_MB`
(2000 in production). The dedicated rejection message is a recorded exception
to ADR-0017.

Measured facts that shape this design (yt-dlp 2026.x, verified live):

- With `max-filesize` set, yt-dlp reads the exact size from the transfer
  response **before sending file data**: the VK streams were refused
  (`File is larger than max-filesize (2889135013 bytes > ...)`) with zero bytes
  written and no file created.
- On such a refusal yt-dlp **exits 0** — a skipped download is not an error.
  The wrapper would currently misreport it as
  "yt-dlp finished successfully, but no downloaded file was reported."
- The cap check lives in the HTTP downloader and only fires when the length is
  known at transfer start; format `tbr` metadata is unreliable for estimates
  (summed 2840 kbps predicted ~10 GB for the file that actually weighs
  3.64 GB), so bitrate-based pre-estimation is rejected.

## Goals / Non-Goals

**Goals:**

- An over-limit Telegram download never completes into a kept file, whatever
  the extractor publishes, and the requester always gets the actionable
  "choose another quality" message.
- Bounded waste: no file data transferred when the size is known earlier
  (metadata or transfer start).

**Non-Goals:**

- Capping or estimating non-Telegram downloads.
- Removing the delivery-time backstop (last line of defense, unchanged).
- Mid-transfer byte accounting across merged streams (the final guard bounds
  the outcome instead).

## Decisions

1. **The transfer cap is passed to the downloader, not enforced by us.**
   `max-filesize` makes yt-dlp refuse oversized transfers itself before file
   data flows. Alternatives rejected: killing the process from the progress
   callback (partial `.part` litter, per-stream byte resets, fragile percent
   parsing) and bitrate-based pre-estimation (measured ~3x overshoot → false
   rejections).

2. **A cap refusal is recognized by its message line.** The wrapper checks for
   the refusal line before the exit-code logic on both the success path
   (exit 0, no file) and the failure path, and reports it as the error reason.
   The service maps that reason to the existing `sizeExceeded` outcome — stored
   reason with the real sizes in MB, permanent failure, the dedicated
   `notifyFileTooLarge` message branch and no retry, all unchanged from
   `telegram-upload-size-guard`. Alternative rejected: relying on the generic
   "no downloaded file" error, which hides the cause (the exact problem
   ADR-0017's exception exists for) and may schedule a pointless retry.

3. **The metadata estimate stays as the first layer.** When sizes are
   published (YouTube) the rejection happens before the transfer process even
   starts. Both layers compare against the same `TELEGRAM_MAX_UPLOAD_MB`, so
   they and the delivery backstop cannot disagree.

4. **A completed but oversized file is deleted and reported as the size
   rejection.** This is the only layer that covers sizes revealed neither in
   metadata nor at transfer start (e.g. length-less streams). Deletion is the
   literal reading of "such files must not be downloaded": the file never
   becomes a Source, never reaches the library, and never reaches delivery.
   Interrupted downloads elsewhere keep using the existing orphan pruning
   (ADR-0010).

5. **The cap applies per downloaded stream; the final guard owns the merged
   total.** yt-dlp refuses each part against the cap, so a merged file whose
   parts are individually under the limit but whose sum is over it is caught by
   the final guard.

## Risks / Trade-offs

- **Message-text coupling:** refusal recognition keys on the downloader's
  wording. If it changes, the failure degrades to the generic "no downloaded
  file" error (generic message, possible retry) rather than a silent success —
  tests pin both the marker path and the fallback path.
- **Deletion is irreversible.** It only triggers for Telegram-origin tasks
  whose completed file exceeds the configured limit, i.e. files the requester
  was told not to expect; the recorded reason keeps the real size for
  administrators.
- **A size that never reveals early still transfers once** before the final
  guard removes it — bounded by the extractor, accepted as the cost of not
  killing processes mid-transfer; revisit with byte accounting if it shows up
  in practice.
