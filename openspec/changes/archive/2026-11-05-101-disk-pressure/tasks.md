# Tasks

## 1. Disk pressure guard

- [x] 1.1 Add `DiskSpacePolicy` + `InsufficientDiskSpaceException` and `MIN_FREE_DISK_MB`
- [x] 1.2 Enforce in `DownloadDispatcher` before persisting
- [x] 1.3 Map the rejection in the UI, API v1/v2 (507) and Telegram
- [x] 1.4 Show the warning in the shared disk card
- [x] 1.5 Add translations (ru/en) and the `Disk Pressure` glossary term

## 2. Automatic cleanup

- [x] 2.1 Add the hourly `downloads-prune` supervisor program
- [x] 2.2 Add `docker builder prune --all -f` and the `var/log` purge to the deploy
- [x] 2.3 Add the disk-space runbook and link it from README

## 3. Tests and verification

- [x] 3.1 Unit tests for the policy and the dispatcher guard
- [x] 3.2 Controller tests for the UI/API rejection and the Telegram callback
- [x] 3.3 Run `make lint`, `make test`, `make openspec-validate`

## 4. OpenSpec

- [x] 4.1 Validate and archive the change
