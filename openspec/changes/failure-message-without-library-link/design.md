# Design

## Context

The wrapper invokes yt-dlp with `--print after_move:filepath` to learn the final
file path. In yt-dlp that option sets quiet mode (`opts.quiet = True`), and
`to_screen()` drops every message while quiet — including the transfer-cap
refusal line emitted by the HTTP downloader when Content-Length exceeds
`--max-filesize`. A cap refusal therefore exits 0 with completely empty output:
the wrapper reports "finished successfully, but no downloaded file was
reported", and the service maps it to the generic failure path instead of the
size rejection (task 231 on production reproduced this exactly). See
proposal.md for the motivation of the message-content change.

## Goals / Non-Goals

**Goals:**

- A cap refusal is recognized and reported as a size rejection even when yt-dlp
  prints nothing.
- The generic Telegram failure message carries no web-library URL.

**Non-Goals:**

- Changing quiet mode or the `--print`-based path detection (progress and error
  handling depend on the current output shape).
- Changing the delivery-failure message, the stored failure reason, or the
  Re-download action.

## Decisions

- **Detect the refusal by outcome, not by wording.** When a transfer cap was
  requested and yt-dlp exits 0 without reporting any file, the wrapper reports
  a refusal-style error (`larger than max-filesize` wording) so the existing
  service mapping to `sizeExceeded` applies unchanged. Alternatives considered:
  forcing verbose output (restores the line but floods stderr and would break
  the failure-reason extraction, which takes the first non-progress line), and
  dropping `--print` (breaks reliable file-path detection).
- **Keep the refusal-line scan.** When yt-dlp is not quiet (e.g. future option
  changes) the real line carries exact byte sizes, which the service turns into
  the precise "File size N MB exceeds…" reason; the synthetic wording carries
  no sizes and falls back to the limit-only reason.
- **Remove the URL in the notifier, not in translations.** The URL is composed
  in code (`TELEGRAM_HOST_URL` + `/ui/source`); the translation key holds only
  the generic sentence, so the text becomes the translation alone.

## Risks / Trade-offs

- [An empty successful run under a cap that is *not* a size refusal (e.g. an
  extractor yielding nothing) is reported as a size rejection] → acceptable:
  every such outcome is already an error, and the requester-facing advice
  ("choose another quality") is harmless; the cap is only set for
  Telegram-origin tasks where a size interpretation is the likely truth.
- [Requesters lose the direct library pointer on failures] → intentional per
  the requester's ask; successful and delivery-failure flows still surface the
  library.

## Migration Plan

Standard release: tag, CI builds the image, deploy workflow migrates and
restarts. No data migration; rollback = previous tag.

## Open Questions

None.
