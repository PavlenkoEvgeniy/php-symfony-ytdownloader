# Design

## Context

yt-dlp is run with the downloads directory as its output path. When it aborts
mid-transfer (transfer cap, network error, timeout, killed worker) it leaves
whatever it had already written — `.part` partials and fully downloaded
intermediate stream files such as DASH audio branches — in that directory;
only a *completed* oversized file had explicit removal. The size-rejection
message is produced by `TelegramNotifier::notifyFileTooLarge` and carries no
inline keyboard, while the initial link intake (`TelegramUpdateHandler::handleUrl`)
already knows how to store a pending selection token in the cache and render
the quality keyboard.

## Goals / Non-Goals

**Goals:**
- No file of a non-successful attempt survives in the downloads directory.
- Interrupted attempts (worker/process death) are eventually cleaned up.
- The size-rejection message is followed by a usable, filtered quality
  selection for the same link.

**Non-Goals:**
- Filtering qualities at the initial link intake.
- Resuming partial transfers across attempts.
- Changing the generic failure message or the Re-download flow.

## Decisions

- **Per-attempt staging directory inside the downloads directory.** The
  wrapper points the downloader's output path at
  `<downloads>/.work/<random>` for the run, moves every reported finished
  file to the downloads directory afterwards, and removes the staging
  directory in a `finally` block — on success, failure, refusal and timeout
  alike. Alternatives rejected: a directory *next to* the downloads
  directory may live on another filesystem when the directory is bind-mounted
  (a move would then copy gigabytes or fail); a before/after directory diff
  can delete a concurrent download's fresh file; pattern-matching leftover
  names depends on title sanitization and stays guesswork.
- **Staging lives inside the downloads directory** because the orphan pruner
  only ever considers files (directories are left alone), so the area is
  invisible to the orphan list and to library tooling, while sharing the
  filesystem makes the final move an atomic rename.
- **Sweep of stale staging directories on every run**: a directory older
  twice the process timeout (24 h when no timeout is configured) is removed
  at the start of the next attempt. The bound is safe because a live run can
  never outlive the process timeout.
- **No resume across attempts**: partials are discarded with the staging
  directory. Accepted: retries are rare and the previous resumability was
  never relied upon; bounded disk usage matters more.
- **The quality offer is a second message, sent by
  `TelegramUpdateHandler`**, not attached to the rejection message: the
  filtered selection needs up to three metadata probes (one per remaining
  quality, capped at 20 s each), and the rejection message itself must reach
  the requester immediately. The notifier stays a plain text sender.
- **Filtering reuses `FormatResolver` + `DownloadSizeEstimator`** with the
  configured upload limit: per quality, resolve the format string and treat
  an estimate above the limit as "not offered"; an empty estimate (probe
  failed or sizes unknown) means "still offered" — the transfer-time guard
  remains the hard limit. The rejected quality is skipped without probing:
  repeating it is exactly what the existing "no Re-download" rule avoids.
- **The offer stores the same pending-token structure as the initial
  intake**, so the existing `dl:<token>:<quality>` callback path queues the
  new task unchanged; it is stored only when at least one quality is
  offered.

## Risks / Trade-offs

- Up to three sequential probes delay the second message (worst case about
  a minute on a slow site); the rejection text is already delivered, and the
  path only runs after a rejection.
- A pending selection the requester opened for a *new* link can be replaced
  by a late rejection offer; the older keyboard then reports "expired" and
  the link must be sent again. Rare, self-healing, accepted.
- The sweep runs only when a later attempt starts; a service that stops
  receiving downloads keeps stale staging data until the next run or a
  manual prune.
