# Tasks

## 1. Attempt liveness

- [x] 1.1 Add a per-task lease service (non-blocking `flock`, released on destruct)
- [x] 1.2 Acquire the lease in `DownloadMessageHandler` before claiming and release it in `finally`
- [x] 1.3 Let `claimProcessing` accept `queued` and `processing`, keeping `success`/`error` untouched

## 2. Reset

- [x] 2.1 Make Reset per task: skip tasks with a held lease, reset free ones
- [x] 2.2 Return the skipped count and show it in the admin flash and audit metadata
- [x] 2.3 Update the Active Tasks confirmation text and translations

## 3. Long download timeout

- [x] 3.1 Run yt-dlp through a streaming Symfony Process with `--newline`
- [x] 3.2 Configure `yt_dlp.timeout` to 6 hours and keep the probe at 20 seconds
- [x] 3.3 Stop the process on timeout and return a readable error

## 4. Tests and verification

- [x] 4.1 Unit tests for the lease and for Reset skipping a live task
- [x] 4.2 Handler test: duplicate delivery is ignored; redelivery after a dead attempt re-runs
- [x] 4.3 Run `make lint`, `make test`, `make openspec-validate`

## 5. OpenSpec

- [x] 5.1 Sync the spec and archive the change
