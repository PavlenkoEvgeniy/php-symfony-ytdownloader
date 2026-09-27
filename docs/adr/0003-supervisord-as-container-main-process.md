# Supervisord runs as the container main process

The php-fpm container must run the queue workers, but supervisor was started by a
manual `make supervisor-step` after `docker compose up` — every restart that skipped
that step left the container alive with no worker, and all new downloads silently
stayed `queued` (this produced the exit-code-0 image bug of v1.3.2 and the
"stuck on queued" incident of v1.3.5). We decided the container starts
`supervisord` as PID 1 (via `CMD ["supervisord", "-n"]` behind the
`ensure-app-dirs.sh` entrypoint) with `php-fpm` and `messenger-consume` as
supervised programs, so a bare `docker compose up` always runs the workers.
The container is therefore self-sufficient; the `make supervisor-*` targets remain
only for rereading the worker config on a live container.

## Considered Options

- `CMD ["php-fpm"]` plus a manual supervisor start — rejected: it is exactly the
  arrangement that kept producing "container up, queue frozen" incidents.
- A separate worker container running only `messenger:consume` — cleaner process
  isolation, but rejected for now: it doubles the deployment surface (image, env,
  compose service) of a single-box personal service to solve a problem that
  supervisord already solves in-process.