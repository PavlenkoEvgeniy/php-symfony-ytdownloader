# Migrations touching the messenger table must be idempotent

The doctrine messenger transport creates its `messenger_messages` table itself
(auto-setup) the first time a worker touches it — and workers start before
migrations run, both in `make init` (supervisord boots them with the container,
ADR-0003) and on deploy (the VPS worker is already consuming while `migrate`
executes). So auto-setup and the migrations race for the table's creation, and
any migration that creates it unconditionally fails with `Duplicate table` when
it loses. We decided that the auto-setup schema is canonical
(`messenger_messages` plus the composite `idx_messenger_messages`), and every
migration that touches that schema checks `information_schema` first and is a
no-op when the table already exists — `Version20250504081417`,
`Version20260923130000`, and the alignment migration `Version20260928100000`
which converges the divergent states (old single-column indexes, an unused
`pg_notify` trigger) onto the canonical schema.

## Considered Options

- Sequencing in `make init` — stopping the workers around
  `doctrine:migrations:migrate` — rejected: the same race exists on every
  deploy and in CI, so the fix would have to be repeated per environment and
  still depends on orchestration never drifting.
- Dropping migrations for the messenger table entirely and relying on
  auto-setup — rejected: migrations are the only schema source applied to a
  fresh database, and `migrations:migrate` on an empty schema would then
  validate against a table that only exists as a side effect of a worker.

## Consequences

- Never edit an already-shipped migration to add such a guard: databases where
  it is already marked executed will never run the new statements. Converging
  changes go into a new migration.
- The `pg_notify` trigger was removed as unused: no `LISTEN`/`use_notify`
  consumer is configured, and the transport works with plain polling.