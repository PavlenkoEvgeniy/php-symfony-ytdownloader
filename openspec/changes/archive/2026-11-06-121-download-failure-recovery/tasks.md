# Tasks

## 1. Failure result and classification

- [x] 1.1 Add `DownloadResult` and `DownloadFailureKind`
- [x] 1.2 Return the result from `VideoProcessorInterface`/`VideoDownloaderService` and store the reason
- [x] 1.3 Add `DownloadFailureClassifier` with transient/permanent patterns

## 2. Automatic retry

- [x] 2.1 Schedule a delayed retry for transient failures below the attempt limit
- [x] 2.2 Mark `error` and notify only on the final attempt or a permanent failure
- [x] 2.3 Clear the stored error when a fresh attempt starts and on success

## 3. Manual re-download

- [x] 3.1 Add `DownloadRetryService` (error → queued, reset attempts, dispatch, audit)
- [x] 3.2 Add the admin action and buttons on the Active Tasks page
- [x] 3.3 Add the Telegram failure message with a Re-download button and its callback

## 4. Translations and audit

- [x] 4.1 Add the audit action, labels and messages (en/ru)
- [x] 4.2 Update CONTEXT.md terms for Retry and Re-download

## 5. Tests and verification

- [x] 5.1 Unit tests for the classifier, retry scheduling and re-download
- [x] 5.2 Controller/callback tests for admin and Telegram re-download
- [x] 5.3 Run `make lint`, `make test`, `make openspec-validate`

## 6. OpenSpec

- [x] 6.1 Sync the spec and archive the change
