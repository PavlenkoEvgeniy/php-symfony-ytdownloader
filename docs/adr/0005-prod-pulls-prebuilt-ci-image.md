# Production pulls the prebuilt CI image instead of building on the VPS

Deploying a tag ran `docker compose up -d --build` on the VPS: a full image
rebuild (apt packages, composer, PHP extensions) took ~10 minutes, and the
deploy script is wrapped by `appleboy/ssh-action` with a 10 minute command
timeout — the v1.3.8 deploy died with "Run Command Timeout" right before
`doctrine:migrations:migrate` ran. Meanwhile the lint-and-test workflow
(invoked by the deploy workflow itself via `workflow_call`) already builds and
pushes a php-fpm image to GHCR tagged `ci-<sha>` on every run. We decided the
production deploy pulls `ghcr.io/pavlenkoevgeniy/php-symfony-ytdownloader/php-fpm:ci-<sha>`
via a `docker-compose.prod.yml` override (`make pull`) instead of building:
deploys shrink from ~10 minutes to ~1–2 and production runs exactly the image
that passed lint and tests in the same run. Application code and vendor still
come from the git checkout and `composer install` on the VPS — the compose file
bind-mounts the working tree, so the image is only the runtime.

## Considered Options

- Only raise `command_timeout` — keeps deploys passing but leaves every release
  rebuilding the image from scratch for ten minutes.
- Pull `ci-latest` — rejected: it tracks the latest master push, so a tag cut
  from an older commit would deploy someone else's image. `ci-<github.sha>` is
  exact, and the checks job of the same deploy run has just pushed it.
- A separate CI job building version-tagged images (`v1.3.8`) — rejected:
  redundant, `ci-<sha>` of the tag commit already exists by deploy time.