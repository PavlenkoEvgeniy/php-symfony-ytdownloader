# Design

## Context

A failed Telegram download currently produces a message with the recorded
reason, which is the first meaningful line of `yt-dlp` output. For a bot user
that line is noise and can leak internals (HTTP status codes, host details). The
reason is still valuable to an administrator and is already shown in the Admin
Panel.

## Goals / Non-Goals

**Goals:**

- Show the requester a short, actionable failure message.
- Keep the Re-download action and the library link.
- Keep the recorded reason for the administrator.

**Non-Goals:**

- Removing or changing the recorded reason.
- Changing the web or API error responses.

## Decisions

- **Generic message instead of the reason.** The requester sees a fixed text. The
  reason is not passed to the notifier at all, so a future change cannot start
  leaking it into the message by accident.
- **Reason stays on the task.** `error_message` is still recorded and shown in
  the Admin Panel; only the Telegram message changes.
- **Message shape is unchanged.** The generic text replaces the reason line; the
  library link and the inline `retry:<taskId>` button stay as they are.
