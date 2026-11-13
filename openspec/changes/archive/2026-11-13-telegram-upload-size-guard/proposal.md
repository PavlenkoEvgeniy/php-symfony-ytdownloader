# Proposal

## Why

Telegram cannot deliver a file larger than the configured upload limit
(`TELEGRAM_MAX_UPLOAD_MB`: 2000 MB behind the local Bot API, 50 MB on the cloud
API, ADR-0008). Today the service downloads such a file completely — spending
traffic, disk space and minutes of worker time — and only the delivery step then
refuses it, while the requester gets no hint that a lower quality would have
worked. The downloader already knows the expected size before transferring any
bytes, so an over-limit download can be rejected upfront with an actionable
message.

## What Changes

- Before starting the transfer for a **Telegram-origin** Download Task, the
  service estimates the final file size for the resolved quality from downloader
  metadata and compares it with the Telegram upload limit.
- If the estimate exceeds the limit, the attempt is rejected without downloading:
  the task ends in `error` with a stored reason, the failure is permanent (no
  automatic retries), and no file is written.
- The Telegram requester receives a specific, actionable message — the file at
  this quality is too large for Telegram, choose another quality — instead of the
  generic failure text (a deliberate, narrow carve-out from ADR-0017; the
  recorded reason stays admin-only).
- When the size cannot be estimated from metadata, the download proceeds as
  today; the existing delivery-time size check remains as the backstop.
- Web UI and REST API tasks are unaffected: they have no upload limit, because
  the file is served from the Library.

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `download-recovery`: a new requirement defines the pre-transfer size rejection
  for Telegram-origin tasks (estimate, permanent failure, stored reason, no
  transfer); the existing requirement about the Telegram failure message is
  amended so that a size rejection gets its own actionable message while all
  other failures keep the generic one.

## Impact

- Affected code: `VideoDownloaderService` (probe before download), a new
  size-estimation service built on the downloader metadata call
  (`YoutubeDlProbe`-style `extractInfo`), `DownloadResult` / failure kind to
  carry the size rejection, `DownloadMessageHandler` (notification branch),
  `TelegramNotifier` (new message method), `translations/messages.{en,ru}.yaml`.
- Configuration: reuses `TELEGRAM_MAX_UPLOAD_MB`; no new settings, no DB or API
  schema changes.
- Docs: `docs/adr/0017-telegram-failure-hides-the-reason.md` gets a follow-up
  note (or a superseding ADR) recording the single actionable exception.
- No GitHub issue exists for this change; motivation came from a direct request.
