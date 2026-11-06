# Queue purge deletes task rows and pending transport messages

The Admin Panel's purge action deletes every `queued` download task and, as part of the same operation, every pending message (`delivered_at IS NULL`) from both messenger transports (`download_queue` and `failed_queue`). Task rows alone do not stop work: the worker consumes messages independently of the task rows, so a purge that only removed rows would leave downloads running in the background. Wiping `failed_queue` too means tasks in `error` lose their pending messages permanently, but no retry mechanism exists or is planned — a re-run would be a fresh task. (ADR 0014 later added automatic retries and a user-initiated Re-download; purge still deletes without retrying.)

`DownloadMessageHandler` claims the task row with an atomic `UPDATE ... WHERE id = :id AND status IN ('queued', 'processing')`; a missing row makes the claim fail and the handler returns without downloading. The processing lease (ADR 0013), not the status alone, guarantees one live attempt. The purge race described in the original design is therefore closed by the claim, not by a separate guard.

## Considered Options

- Requeue ("reset" to `queued`) — rejected: the actual need was clearing an accumulation of junk tasks, not re-running them.
- Separate handler guard for missing task rows — not needed: the claim in `DownloadMessageHandler` already skips a message whose task row is gone.
- Purging only `download_queue` — rejected: leaves dead `failed_queue` messages that can never be retried anyway.
