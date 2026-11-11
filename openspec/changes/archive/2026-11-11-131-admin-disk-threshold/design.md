# Design

## Context

The disk pressure guard lives in a policy that currently receives the threshold
(`MIN_FREE_DISK_MB`, default 5120) via container wiring from the environment.
Admin-managed runtime settings already have a home: the `DownloadSettings`
singleton edited on the Admin Panel Settings page (`web_intake_enabled`,
`progress_interval_minutes`), read through a repository interface that returns
an unsaved defaults instance when the row is missing. Relevant ADR:
[ADR-0010](../../../docs/adr/0010-reject-downloads-under-disk-pressure.md)
(reject downloads under disk pressure) — it names `MIN_FREE_DISK_MB` as the
mechanism and gets a correction line from this change. See `proposal.md` for
motivation and `specs/` for the required behavior.

## Goals / Non-Goals

**Goals:**

- Threshold editable at runtime from the existing Settings page, with the
  environment variable demoted to the factory default.
- Effect visible immediately after saving; no new caching layer.

**Non-Goals:**

- Automatic cleanup or freeing of disk space.
- Per-surface or per-user thresholds.
- Changing rejection messages or status codes (507 / flashes / bot reply).
- Aborting Download Tasks that are already accepted.

## Decisions

- **Store the threshold in the `DownloadSettings` singleton, not a new
  entity.** It is the same class of operational knob as the web intake switch
  and the progress interval; a separate entity or page for one number adds
  navigation and CRUD surface with no benefit. Alternative considered: a
  generic key-value settings table — rejected as over-engineering for a single
  column and it loses per-field validation typing.
- **Nullable column; `NULL` means "follow the environment default".** The
  migration adds `min_free_disk_mb` as `NULL`, and the policy resolves
  `NULL → MIN_FREE_DISK_MB (5120)`. Alternative considered: `NOT NULL DEFAULT
  5120` — rejected because installs that already have a settings row but tuned
  the threshold in `.env` would be silently overridden by the migration.
- **Read the threshold from the repository on every check, no caching.** One
  extra `SELECT` on intake requests is negligible, and an admin saving the form
  sees the effect on the very next request. A request- or worker-level cache
  was rejected: it makes "when did it take effect" indeterminate for no
  measurable gain.
- **Form semantics: non-negative integer MB; empty = follow the environment
  default; `0` disables the guard.** MB matches the existing field name and
  default (`5120`); empty round-trips to `NULL` instead of pinning a concrete
  number, so the deployment default stays reachable after the first save. `0`
  doubles as the off switch, which is cheaper than a separate enable toggle.
- **Amend ADR-0010 with a correction line; no new ADR.** The decision to reject
  downloads under disk pressure is unchanged; only its configuration source
  moves. The three ADR criteria (hard to reverse, surprising, real trade-off)
  are not met by a standalone ADR for this.

## Risks / Trade-offs

- [Admin sets an aggressively low threshold and the volume fills] → Accepted:
  it is an explicit operational choice; the settings page shows live free space
  next to the field to make the trade-off visible.
- [Extra `SELECT` per intake request] → Negligible; the row is tiny and intake
  is not a hot path.
- [Settings row read fails when the database is unavailable] → No new risk:
  intake already requires the database to persist a task.

## Migration Plan

Single additive migration: `ALTER TABLE download_settings ADD COLUMN
min_free_disk_mb INTEGER NULL` (no default). Deploy runs migrate-then-restart
as usual; existing installs keep their current behavior (`NULL` → environment
default) until an administrator saves the field. Rollback = revert the release;
the column carries no data that needs unwinding.
