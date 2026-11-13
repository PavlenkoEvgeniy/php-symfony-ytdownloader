# The Telegram failure message hides the internal reason

The final Telegram failure message used to include the recorded downloader
reason, for example `unable to download video data: HTTP Error 403: Forbidden`
(GitHub #129). For the requester that line is noise, it may expose internals, and
it does not say what to do next. The reason is still useful to an administrator.

## Decision

The bot sends a fixed generic failure text, `Download failed. Please try again
later.`, together with the library link and the **Re-download** button. The
recorded reason stays on the Download Task and is shown in the Admin Panel. The
notifier no longer accepts the reason, so it cannot leak into the message by
accident.

**Exception (change `telegram-upload-size-guard`):** a task rejected by the
pre-download size guard sends its own actionable message — the file is too
large, choose another quality — with no Re-download button, because repeating
the same quality would fail again. Every other failure keeps the generic text.

## Considered Options

- **Keep sending the reason** — it is the previous behavior, but it is technical
  noise for a bot user and may reveal internal details.
- **Send a short failure category** — still technical, and it forces the message
  layer to classify a reason it does not own.
- **Hide the reason everywhere** — loses the diagnostic value in the Admin Panel.

## Consequences

- Requesters see an actionable message instead of a downloader error line.
- The reason remains available to administrators through the task.
- This refines ADR 0014, which introduced the reason in the Telegram message.
