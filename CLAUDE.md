# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Video/audio downloader service (YouTube, Instagram, Telegram, TikTok, etc.) built on **PHP 8.4 + Symfony 7.4**, with EasyAdmin 4, PostgreSQL, Redis, JWT auth (v1 and v2 APIs), and a Telegram bot. Downloads run through `yt-dlp` via the `p3sdev/php-ytdlp-wrapper` package. See `CONTEXT.md` for the project's ubiquitous language and `Readme.md` for API endpoint docs.

## Commands

All commands run **inside Docker containers** via `make` — never install dependencies or run tools on the host.

```bash
make init          # Full setup: env files, containers, composer, JWT keys, DB, supervisor
make restart       # Restart stack (down + up + supervisor + cache)
make stop          # Stop all containers

make test          # PHPUnit (recreates test DB: drop, create, migrate, load fixtures)
make test-coverage # PHPUnit with coverage
make cs-check      # php-cs-fixer dry run (shows diff, no changes)
make cs-fix        # php-cs-fixer fix
make phpstan       # PHPStan level 6
make psalm         # Psalm level 4 (also run with peck/lint below)
make peck          # Grammar/spelling check (peck)
make lint          # cs-fix + phpstan + psalm + peck (full lint)
make security-check # composer audit

make db-setup      # Create DB + run migrations (dev)
make docker-php    # Shell into the php-fpm container (alias: make bash)
make docker-pgsql  # Shell into the postgres container
```

Run a single PHPUnit test (from inside the container):

```bash
make docker-php
php bin/phpunit --filter TestClassNameOrFilter
```

The test DB is rebuilt (dropped, created, migrated, fixtures loaded) by `make test` before phpunit runs; when iterating on a single test, the DB setup steps can be skipped and only `php bin/phpunit --filter ...` re-run. Fixtures are grouped; `make test` loads group `all`.

CI (`.github/workflows/lint-and-test.yml`) runs: php-cs-fixer, phpstan, psalm, peck (grammar), composer audit, and PHPUnit — each in the prebuilt php-fpm image pulled from GHCR. Deploy (`.github/workflows/deploy.yml`) auto-runs on successful lint-and-test of `master`: SSH to VPS, `git pull`, rebuild, migrate, clear cache.

## Architecture

### Download flow (the core domain)

1. **Entry points** create a download request: web UI (`Controller/Ui/DownloadController`), REST API v1/v2 (`Controller/Api/V1|V2/DownloadController`), or Telegram bot (`Controller/Telegram/TelegramController` → `TelegramBotService`).
2. `Service/DownloadDispatcher` **persists a `DownloadTask` entity first** (so it is visible as `queued`), then dispatches a `Message/DownloadMessage` on the Messenger bus — this ordering guarantees every dispatched message has a matching task row.
3. Transport is **Doctrine (PostgreSQL)**, not a message broker: `config/packages/messenger.yaml` routes `DownloadMessage` to the `async` transport (`download_queue` table), with a `failed` transport for retries-exhausted messages. RabbitMQ was removed — do not reintroduce broker queues.
4. A **supervisor-managed worker** inside the `ytdownloader-php-fpm` container consumes messages (`make supervisor-start`). If the worker dies mid-task, the task row stays `processing` until redone — `processing` means "picked up", not "guaranteed running".
5. `MessageHandler/DownloadMessageHandler` marks the task `processing` via `DownloadTaskManager`, calls `VideoDownloaderService` (implementation of `Service/VideoProcessorInterface`), then marks `success`. `EventSubscriber/DownloadTaskFailureListener` handles the `error` state.
6. `VideoDownloaderService` resolves the format via `FormatResolver` (best|moderate|poor|audio), downloads via `YoutubeDlWrapper`, persists each result as a `Source` entity (`SourceManager`), logs progress rows via `LogManager` (shown in the admin UI), and notifies the requester through `TelegramNotifier` when a Telegram user id is attached.
7. `QueueStatsService` reports queue counters derived from task statuses in the DB.

Key rule from `CONTEXT.md`: a **task is the database record of the work, not the work itself**; avoid the terms "job" and "broker queue" in code and docs.

### Layers

- **Controllers** are grouped by surface: `Ui/` (Twig frontend), `Api/V1/` and `Api/V2/` (JWT; v2 adds refresh tokens via `RefreshTokenManager` + `Entity/RefreshToken`), `Admin/` (EasyAdmin CRUD for Source, Log, User), `Telegram/`, `HealthCheck/`.
- **Entities** (`Entity/`): `User`, `Source` (downloaded file), `DownloadTask`, `Log`, `RefreshToken`. Gedmo timestampable/blameable extensions are in use.
- **Services** in `Service/` hold the business logic; controllers stay thin. Use `VideoProcessorInterface` as the injection point for download processing.
- **Security** (`Security/`, `config/packages/jwt`): form login for the web UI (`UserAuthenticator`), JWT authenticators for both API versions; v2's success handler issues refresh tokens. `UserChecker` blocks disabled users.
- **Rate limiting**: `RateLimiter/RateLimitAttribute` + `RateLimitEventListener` — apply via attribute on controllers.

### Infra / environment

- Containers (`docker/docker-compose.yml`): `ytdownloader-webserver` (nginx), `ytdownloader-php-fpm`, `ytdownloader-pgsql`, `ytdownloader-redis`. CI uses `docker/docker-compose.ci.yml` with a prebuilt image (no local build).
- Env files (`.env`, `.env.local`, `.env.test`, docker env) are **generated by `make env-setup`** (`bin/generate-env.sh`) — don't create them by hand; secrets live in `.env.local` (gitignored-ish, regenerated per machine).
- JWT key pair is generated by `make generate-jwt-keypair`.
- Database lives in `./database/` (pgdata bind mount); `make db-purge` wipes it.
- Admin user is created manually: `make docker-php` → `php bin/console app:user-add <username> [password]`.

## Conventions

- All PHP files are `declare(strict_types=1)`; classes are `final readonly` where possible (DTOs, services, handlers).
- Code style: `@Symfony` php-cs-fixer rules plus aligned `=` / `=>` operators and single-space concatenation (`.php-cs-fixer.dist.php`). Always run `make cs-fix` before committing — CI enforces it.
- Static analysis must pass at **PHPStan level 6** and **Psalm level 4**; new code must not weaken these. There is a phpstan baseline (`phpstan-baseline.neon`) — don't grow it.
- `peck` checks spelling in comments/docblocks/identifiers against a Symfony preset — write comments in correct English; project-specific words are allowed via `peck.json`.
- Vendor patches are managed by `cweagans/composer-patches` (`patches/` + `extra.patches` in `composer.json`) — currently a Psalm-compat patch for `illuminate/collections` used by the yt-dlp wrapper. When updating psalm-related dependencies, this patch may need revisiting.
- Tests live in `tests/` mirroring `src/`; they run in the `test` env with fixtures loaded (see `tests/bootstrap.php`, `tests/object-manager.php` for phpstan-doctrine).