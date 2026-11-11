# Disk space runbook

The VPS has a 20 GB SSD. Download media lives in `./var/downloads`, the database
in `./database/`, and Docker data under `/var/lib/docker`. New Download Tasks are
rejected while free space on the download volume is below the configured minimum
(5 GB by default). The threshold is edited on Admin → Settings → Disk space, in
megabytes: an empty field follows `MIN_FREE_DISK_MB` in `.env` (the factory
default) and `0` turns the check off. A change applies to new requests
immediately; tasks already accepted keep running.

## Check

- `df -h /`
- Admin → Active Tasks → Disk Space, or the disk card on the downloads page; the
  card shows a warning while the service is in Disk Pressure.
- `docker system df`
- `docker exec -u www-data -e HOME=/tmp ytdownloader-php-fpm php bin/console app:downloads:prune --dry-run`
- `docker exec -u www-data -e HOME=/tmp ytdownloader-php-fpm php bin/console app:library:prune --dry-run`

## Free space

Run the dry runs first; then repeat without `--dry-run`.

1. Orphan files:
   `docker exec -u www-data -e HOME=/tmp ytdownloader-php-fpm php bin/console app:downloads:prune`
2. Library retention:
   `docker exec -u www-data -e HOME=/tmp ytdownloader-php-fpm php bin/console app:library:prune --days=1`
3. Docker images and build cache: `make docker-prune` (also runs
   `docker builder prune --all -f`).
4. Symfony cache and stale logs: `make cache-purge`, `make log-purge`.
5. Audit retention:
   `docker exec -u www-data -e HOME=/tmp ytdownloader-php-fpm php bin/console app:audit:prune --days=180`
6. Database bloat (the database is small; optional):
   `docker exec ytdownloader-pgsql sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "VACUUM (ANALYZE)"'`

## Never delete

- `ytdownloader_caddy-data` and `ytdownloader_caddy-config` — TLS/ACME state.
- `ytdownloader_pgsql` data (`./database/`) — the library and task history.
- `telegram-bot-api-data` and the Redis volume without a backup.

## After freeing space

Wait for the next request; the guard checks free space on every accepted
download. The disk card warning disappears once free space is above the
threshold.
