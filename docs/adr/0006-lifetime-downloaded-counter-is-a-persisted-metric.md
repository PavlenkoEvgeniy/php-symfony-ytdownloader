# The lifetime downloaded counter is a persisted metric, not a derived sum

The "Total downloaded" counter must survive deleting downloaded files, but the
previous behaviour derived it as `SUM(size)` over `Source` rows, so a cleanup of
the library reset it to zero (issue #77). The counter is now a singleton
`download_metric` row: seeded from `SUM(source.size)` at migration time and
incremented in the same flush that stores each new `Source`. Deleting sources
never reduces it.

## Considered Options

- **A `size` column on `DownloadTask`, totalled over successful tasks** — old
  tasks carry no size (history resets at release), partially downloaded tasks
  marked `error` are missed, and `VideoProcessorInterface::process()` would have
  to change; rejected.
- **Summing `Log` rows (`type = 'success'` already carry a size)** — no migration
  at all, but logs are deletable through the admin panel and nothing in the
  domain promises they persist; rejected.
- **A persisted singleton metric (chosen)** — deleting sources never affects it,
  it survives every task lifecycle (purge only removes `queued` tasks), and it
  keeps history as far as it still exists in the database at migration time.

A repeat download of a file whose `Source` row still exists does not increment
the counter (the dedup check by filename skips the row creation): the metric
counts "bytes that ever landed in the library", not raw transfer volume.