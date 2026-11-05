# Proposal

## Why

The VPS has a 20 GB SSD. Docker images (3.5 GB), the build cache (1.16 GB),
stale `var/log` files (21 MB) and downloaded media accumulate; orphan files from
failed downloads are pruned only manually, and nothing stops a download request
when the disk is nearly full. A single Telegram file can be 2 GB and a web
playlist can be larger, so a download can fill the remaining space and break the
service.

## What Changes

- Add a **Disk Pressure** guard: when free space on the download volume is below
  the configured minimum (5 GB by default), reject new Download Tasks before they
  are persisted. The UI shows a message, REST API v1/v2 return 507 Problem
  Details, the Telegram bot replies with a message; no task is created.
- Prune Orphan Files automatically once an hour through supervisor, alongside the
  existing hourly Library Retention.
- Remove unused Docker build cache in `make docker-prune` and stale `var/log`
  files on deploy.
- Surface low disk space in the admin/download-page disk card.
- Add a disk-space runbook for manual cleanup.

## Capabilities

### New Capabilities
- `library`: the Sources available for download, including retention and
  pruning. This change adds automatic orphan pruning.

### Modified Capabilities
- `download-intake`: add a requirement that new Download Tasks are rejected
  under Disk Pressure on every surface.

## Impact

- `DownloadDispatcher`, UI/API/Telegram create paths, translations, supervisor,
  Makefile/deploy, docs, tests.
- Out of scope: the php-fpm image slimming (separate change `chore/php-fpm-alpine`).
- The threshold is configured with `MIN_FREE_DISK_MB` (default 5120).
