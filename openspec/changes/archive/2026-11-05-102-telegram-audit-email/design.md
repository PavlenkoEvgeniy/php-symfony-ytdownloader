# Design

## Context

The Audit Trail resolves the actor from the authenticated token for the UI and
the REST APIs. The Telegram webhook is not authenticated: the bot resolves the
linked User itself, and the audit call sites pass `actorType: telegram` with the
numeric chat id as the label, leaving the actor id empty. Every Telegram call
site already has the linked `User` in scope — the dispatcher receives it, the
download handler can load it from the task, and the delivery handler resolves it
before writing any audit event.

## Goals / Non-Goals

**Goals:**

- Identify the actor of a Telegram-origin Audit Event by the User's email.
- Keep the Telegram identity available for traceability.
- Keep the Audit Trail consistent with UI/API events, where the actor type is
  `user` and the actor id is populated.

**Non-Goals:**

- Rewriting historical Audit Events.
- Changing what the bot does, what it delivers, or how delivery failures are
  handled.
- Adding new admin filters or a user relation on the Audit Event.

## Decisions

- **The actor is the User, the channel is Telegram.** For Telegram-origin events
  the event carries actor type `user`, the User's id and email, while the channel
  stays `telegram`. The two fields answer different questions: who acted and
  through which surface. Link: ADR 0012.
- **The audit writer accepts an explicit actor.** `AuditLogger::log()` gains an
  optional `User` argument; when it is present it takes precedence over token
  resolution. The call sites already hold the User, so the writer needs no
  repository lookup and the webhook keeps working without an authenticated token.
- **The chat id moves to the metadata.** Events keep `telegram_chat_id` in their
  metadata so an event can still be traced to the Telegram chat after the actor
  label becomes the email.
- **Fall back to the previous shape.** If the task has no linked User, the event
  keeps actor type `telegram` and the chat id as the label: an audit record must
  not lose its actor because the link is missing or was removed.
- **Do not rewrite history.** Audit Events are a record of what was true when the
  action happened; the rule applies to events written after the change. Values in
  `AuditActorType` that describe the old shape stay in the enum for the
  historical rows.

## Risks / Trade-offs

- The email is a snapshot: if the User changes their email later, historical
  events keep the email that was current when they were recorded. That is the
  intended behavior for an audit trail.
- Events now carry an actor id, which was empty for Telegram before; anything
  that filtered by `actorType = telegram` will only find historical and fallback
  events. No such filter exists in the admin today.
- The download handler now loads the task for failed Telegram downloads as well,
  to resolve the actor. The extra read happens once per failed task.
