# Doctrine transport instead of RabbitMQ

The app is a single-user personal service with a single supervisor-driven worker; RabbitMQ was a full extra container (plus management UI, volumes and healthchecks) whose only job was to carry one message type between HTTP requests and the worker. We replaced the AMQP transport with the doctrine transport (`doctrine://default`): messages now live in the `messenger_messages` table alongside the already-persisted download tasks, and queue statistics were already database-based, so the broker was the last piece of infra serving no purpose.

## Considered options

- **`sync://` transport** — simplest, but downloads would block the HTTP/Telegram request and lose retry/failure semantics.
- **RabbitMQ (status quo)** — persistent and proven, but an extra stateful container on a small VPS for negligible throughput, and undelivered messages lived in a broker invisible to the database.
- **Doctrine transport (chosen)** — one less moving part; pending messages survive restarts because they are in the same database; failed messages are inspectable with `messenger:failed:show|retry` instead of the broker management UI.

## Consequences

- A task stuck in `processing` still self-heals only for queued messages: a worker death mid-task leaves the row in `processing` (deliberately not fixed, see `CONTEXT.md`).
- The `messenger_messages` table is provisioned by an explicit migration, mirroring what doctrine auto-setup would create, including the PostgreSQL cleanup trigger for delivered messages.