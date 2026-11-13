# Proposal

## Why

Field test: a Telegram request for a 3.64 GB VK Video file (quality `moderate`)
was fully downloaded and then refused at delivery — the requester got the
generic "download finished, but I could not send the file" fallback and the
oversized file landed in the library. The pre-download size guard shipped in
`telegram-upload-size-guard` failed open because VK metadata carries no
`filesize`/`filesize_approx` for any of its 34 formats, so there was no
estimate to reject on. The requester's requirement stands: a file that cannot
fit the Telegram upload limit must not complete a download, and the requester
must be told to choose another quality.

## What Changes

- Pass the configured Telegram upload limit to the downloader as a transfer cap
  for Telegram-origin tasks, so yt-dlp refuses the transfer when the size
  becomes known at transfer start (it reads the exact length before sending
  file data) even though page metadata had no sizes — this is the VK case.
- Treat a transfer-cap refusal as the existing size-rejection outcome: stored
  reason with the real file size, permanent failure, no retry, the dedicated
  "choose another quality" message instead of the generic failure message.
- Add a final guard after a Telegram download completes: a file that exceeds
  the limit is deleted and reported as the same size rejection, never saved as
  library content and never handed to delivery. This closes the remaining gap
  where neither metadata nor the transfer start reveals the size in time.
- Keep the existing metadata estimate as the first layer (it rejects from
  metadata alone when sizes are published, e.g. YouTube).

Out of scope: the delivery-time backstop (unchanged last line of defense),
non-Telegram downloads (never capped or estimated), and mid-transfer byte
accounting across merged streams (the final guard bounds the outcome instead).

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `download-recovery`: "Oversized Telegram downloads are rejected before
  transfer" is reworded from an estimate-only rejection to a capped transfer
  that never completes an over-limit file (new scenarios for a size revealed
  at transfer start and for a completed-but-oversized file); "The requester is
  told to choose another quality" gains the transfer-cap rejection scenario.

## Impact

- `src/Service/YoutubeDlWrapper(+Interface)`: optional transfer-cap argument
  and recognition of the downloader's cap refusal (it exits 0, so today the
  wrapper would misreport it as "finished successfully, but no downloaded
  file was reported").
- `src/Service/VideoDownloaderService`: pass the cap for Telegram-origin
  tasks, map the refusal to `DownloadResult::sizeExceeded`, final post-download
  size guard with file deletion.
- Specs: `download-recovery` delta. Docs: README paragraph on the cap layers.
- No GitHub issue exists; motivation is the direct field report.
