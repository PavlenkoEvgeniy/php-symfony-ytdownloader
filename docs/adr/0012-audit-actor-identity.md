# Telegram actions are audited under the linked User

Audit Events for actions made through the Telegram bot identified the actor by
the numeric chat id, with the actor type `telegram` and no actor id. The
administrator could not tell who performed the action without looking up the
Telegram Link by hand, while the same person acting through the UI or the REST
API appeared under their email.

We attribute a Telegram-origin action to the linked User: the event carries the
actor type `user`, the User's id and the User's email as the actor label, while
the channel stays `telegram`. The two fields answer different questions — who
acted and through which surface — and the Telegram chat id moves to the event
metadata for traceability. If the task has no linked User, the event keeps the
old shape: actor type `telegram` with the chat id as the label. Already recorded
events are not rewritten.

The audit writer accepts an explicit `User` from the call sites instead of
looking one up: the dispatcher, the download handler and the delivery handler
already hold the linked User, and the Telegram webhook has no authenticated
token to resolve.

## Considered Options

- **Change only the actor label** — minimal, but leaves the actor type describing
  the channel and the actor id empty, inconsistent with UI/API events.
- **Keep the telegram actor type and fill only the id** — still needs a lookup
  rule for the label and keeps two ways to describe the same actor.
- **Look the User up inside the audit writer by chat id** — puts a repository
  dependency into a cross-cutting writer and repeats a lookup every call site
  already has the answer to.
- **Backfill historical events** — rewrites audit history and cannot reliably
  match events whose target rows are already deleted.
- **Attribute to the User (chosen)** — consistent with the other surfaces,
  readable in the admin, and still traceable through the chat id in the metadata.

## Consequences

- `AuditActorType::telegram` remains for historical rows and for the fallback when
  no User is linked; new Telegram events use `user`.
- Events now populate the actor id, so filters by `actorType = telegram` only find
  historical and fallback events. No such filter exists in the admin today.
- The actor label is a snapshot: a later email change does not rewrite historical
  events.
- A task without a linked User still produces an audit event, attributed to the
  Telegram chat id.
