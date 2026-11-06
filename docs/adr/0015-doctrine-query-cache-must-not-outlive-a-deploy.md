# Doctrine query cache must not outlive a deploy

The production Doctrine query cache was stored in Redis
(`doctrine.system_cache_pool` → `cache.system`). When the Telegram progress
interval added a column to `download_settings`, the cached SQL for
`DownloadSettingsRepository::getSettings()` kept selecting the old columns. The
hydrated entity arrived with `progressIntervalMinutes` uninitialized, and the
settings page returned 500 with:

```
Typed property App\Entity\DownloadSettings::$progressIntervalMinutes
must not be accessed before initialization
```

`make cache:clear` clears the filesystem cache only; the Redis pool survived the
deploy and the container restart. The database column and the Doctrine metadata
were both correct, which made the failure look like a template bug.

Production now uses per-request `array` caches for Doctrine's query and result
caches. The old Redis pools are still defined so the deploy can clear the
entries left by previous versions, and `make doctrine-cache-clear` runs after
`make restart` in the deploy. A schema/entity change can no longer serve SQL or
metadata from a previous release.

## Considered Options

- **Clear the Redis pools only on deploy** — fixes this incident but leaves a
  long-lived cache that can go stale between the cache build and the first
  request; the deploy-time clear is kept as a belt-and-braces step.
- **Keep the Redis query cache and bump a cache namespace on every deploy** —
  more moving parts than the query cache is worth for this single-tenant service.
- **Add `progressIntervalMinutes` to a partial-select workaround** — hides the
  stale cache instead of removing the class of bug.

## Consequences

- Query/result caching now lives for one PHP process; the database is fast
  enough for this workload and the settings query is trivial.
- The deploy clears the old Redis Doctrine pools, so entries from before this
  decision do not linger.
- Any future need for a shared query cache must come with an explicit
  invalidation strategy and a deploy-time clear.
