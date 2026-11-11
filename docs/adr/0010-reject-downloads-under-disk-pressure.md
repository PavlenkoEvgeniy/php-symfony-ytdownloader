# Downloads are rejected below a free-space threshold

> **Amendment (2026-11-11, issue #131):** the threshold is now an
> admin-configurable setting on Admin → Settings → Disk space, with
> `MIN_FREE_DISK_MB` kept as the factory default for installs that never set it.
> This supersedes the "persisted admin setting" option and the `.env`-restart
> consequence below: operational experience showed the threshold is tuned often
> enough to earn a place in the admin panel.

The service runs on a 20 GB SSD shared by Docker images, the build cache, the
database, logs and downloaded media. Nothing stopped a new download when the disk
was nearly full: a single Telegram file can be 2 GB, a web playlist can be larger,
and the failure mode was a filled disk that breaks the database and the site.

We reject new Download Tasks on every surface while free space on the download
volume is below 5 GB (`MIN_FREE_DISK_MB`). The check lives in `DownloadDispatcher`
before the task is persisted, so the UI, REST API v1/v2 and the Telegram bot are
covered by one rule. The UI redirects to the Library with an error, the APIs
return 507 Problem Details, and the bot answers the callback. Existing tasks are
not revoked. Rejecting does not prune first: the hourly orphan prune and the
runbook handle space, while deleting files during a rejected request could remove
media the user still wants.

## Considered Options

- **Allow downloads and let the operator clean up after a failure** — the previous
  state; a full disk can corrupt writes and take the site down.
- **Prune before rejecting** — hides the pressure, makes the rejection
  unpredictable, and can delete files the user still needs.
- **Warn only** — the disk card already shows free space, but a warning does not
  stop a 2 GB request.
- **Per-surface or per-user thresholds** — more configuration for a single-tenant
  service; one global threshold is enough.
- **A persisted admin setting instead of an env value** — the threshold is an
  infrastructure limit, not a product decision; changing it requires a restart,
  like other capacity settings. (The web-intake switch remains an admin setting
  because it is a product decision.) *Superseded by the amendment above.*

## Consequences

- A download that starts just above the threshold can still consume the
  headroom; 5 GB leaves room for the largest Telegram upload and a normal
  playlist.
- The guard applies to admins too; freeing space per the runbook is the way back.
- The threshold is visible in `.env` and the runbook; changing it requires a
  restart. *Superseded by the amendment above: the threshold is edited in the
  admin panel, `.env` is only the factory default.*
- A full disk with no active users still needs an external monitor; monitoring is
  out of scope for this decision.
- The hourly orphan prune and `make docker-prune` reclaim space; the runbook lists
  the manual steps.
