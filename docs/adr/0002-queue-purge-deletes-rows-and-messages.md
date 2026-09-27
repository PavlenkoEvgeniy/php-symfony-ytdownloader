# Queue purge deletes task rows and pending transport messages

The Admin Panel's purge action deletes every `queued` download task and, as part of the same operation, every pending message (`delivered_at IS NULL`) from both messenger transports (`download_queue` and `failed_queue`). Task rows alone do not stop work: the worker consumes messages independently of the task rows, so a purge that only removed rows would leave downloads running in the background. Wiping `failed_queue` too means tasks in `error` lose their pending messages permanently, but no retry mechanism exists or is planned — a re-run would be a fresh task.

We deliberately do **not** guard `DownloadMessageHandler` against a missing task row. There is a race window: the worker can deliver a message just before the purge and continue the download after its task row is gone, persisting a `Source` without a task. The race is rare, its outcome (an orphaned source) is harmless for a personal service, and the guard is not worth coupling the handler to the purge concern.

## Considered Options

- Requeue ("reset" to `queued`) — rejected: the actual need was clearing an accumulation of junk tasks, not re-running them.
- Handler guard for missing task rows — rejected: couples the handler to the purge; the race it closes is rare and harmless.
- Purging only `download_queue` — rejected: leaves dead `failed_queue` messages that can never be retried anyway.