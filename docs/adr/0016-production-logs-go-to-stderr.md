# Production logs go to stderr

The prod Monolog handlers used `syslog`, but the Alpine php-fpm container has no
syslog daemon (there is no `/dev/log`) and PHP's `log_errors` was off. As a
result, the settings-page 500 left no trace anywhere: `docker logs` showed only
deprecations, and the project log directory was empty. Diagnosing it required an
isolated reproduction instead of a log line.

Production Monolog now writes the main and developers channels to
`php://stderr`, which Docker captures in `docker logs`. The PHP-FPM ini also
enables `log_errors` and points `error_log` at `/proc/self/fd/2`, so raw PHP
fatals reach the same stream. The deprecation channel already used stderr.

## Considered Options

- **Keep syslog and run a syslog daemon** — another process and a log rotation
  concern for one container; Docker already captures stderr.
- **Write to `var/log/prod.log`** — the log directory is purged on every deploy,
  so an error that causes a bad release could disappear exactly when it is
  needed; stderr survives in the container logs until rotation.
- **Rely on EasyAdmin's error page** — it shows only a generic message and hides
  the exception.

## Consequences

- `docker logs ytdownloader-php-fpm` is the first place to look for a 500.
- Logs are not shipped off the host; a future external collector can read the
  Docker log driver, and the file handler remains available for that decision.
- `MONOLOG_IDENT`/`MONOLOG_DEVELOPERS_IDENT` are no longer used by production.
