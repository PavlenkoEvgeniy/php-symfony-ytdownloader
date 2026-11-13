# Proposal

## Why

Field test of the size guard on v1.15.1: the transfer cap refused the VK download,
but the requester received the generic "Download failed" message instead of the
size rejection. Root cause: `--print after_move:filepath` (always passed by the
wrapper) forces yt-dlp into quiet mode, which suppresses the
`File is larger than max-filesize` refusal line, so the refusal was never
recognized and the task was reported as a generic failure. Separately, the
requester asked that the generic error message no longer print the web-library
URL. There is no GitHub issue for this change.

## What Changes

- An empty successful yt-dlp run under a transfer cap is reported as a cap
  refusal, so the task ends with the size-rejection reason and the requester
  gets the "choose another quality" message (restores the already specified
  behavior of the size guard; root-cause fix for the field report).
- The generic Telegram failure message no longer appends the web-library URL:
  it becomes the generic text plus the Re-download action only.

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `download-recovery`: "The requester is told that a download failed" no longer
  includes a link to the web library; the generic message explicitly omits it.

## Impact

- Out of scope: the delivery-failure message ("finished, but I could not send
  the file") keeps its library link — that file exists in the library and the
  link is its only pointer; the recorded failure reason stays on the task for
  administrators; the Re-download action is kept.
- Code: `src/Service/YoutubeDlWrapper.php` (cap-refusal recognition),
  `src/Service/TelegramNotifier.php` (message text), tests for both and for the
  handler/service mapping.
- Observable: bot error message content; stored reason of cap-refused tasks.
