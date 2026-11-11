# Tasks

## 1. Settings storage

- [x] 1.1 Add a Doctrine migration that adds a nullable `min_free_disk_mb`
      integer column to `download_settings` and verify it applies on a fresh
      test database (`make test` rebuilds it with migrations)
- [x] 1.2 Expose the threshold on the settings entity (constructor default
      `NULL`, getter, setter accepting `NULL` to clear) and verify with a unit
      test that `NULL` round-trips and a saved integer is returned as-is

## 2. Disk pressure policy

- [x] 2.1 Resolve the threshold per call from the settings repository:
      stored value wins, `NULL` falls back to `MIN_FREE_DISK_MB`, `0` disables
      the guard; keep the environment wiring only as the fallback source
- [x] 2.2 Add unit tests covering the three resolution paths (stored value
      applied, `NULL` → environment default, `0` never rejects) and update the
      existing disk-pressure tests to the new construction; verify with
      `php bin/phpunit --filter DiskSpacePolicy`

## 3. Admin Settings page

- [x] 3.1 Add the threshold field to the settings form — non-negative integer
      MB, empty means "follow the deployment default" — with a validation
      message for negative values, and verify the form rejects `-1` in a
      functional test
- [x] 3.2 Show on the Settings page the current free space, whether it is
      below the effective minimum, and the effective minimum with its origin
      (deployment default or administrator-set); verify in a functional test
      that saving a value, clearing it, and the low-space indicator behave per
      the `admin-panel` delta scenarios
- [x] 3.3 Cap the threshold at 1048576 MB so a submitted value cannot overflow
      the megabyte-to-bytes multiplication, and verify a larger value is
      rejected in a functional test

## 4. Documentation

- [x] 4.1 Update `README.md` and `docs/runbook/disk-space.md` to describe the
      threshold as an admin setting with the environment variable as factory
      default, and verify the old "configured only via env" wording is gone
      (`grep -rn MIN_FREE_DISK_MB README.md docs/`)
- [x] 4.2 Add a correction line to `docs/adr/0010-reject-downloads-under-disk-pressure.md`
      noting the threshold is admin-configurable since issue #131 and verify
      the ADR reads consistently with the change

## 5. Integration checks

- [x] 5.1 `make lint` passes (php-cs-fixer, PHPStan 6, Psalm 4, peck)
- [x] 5.2 `make test` passes in full
- [x] 5.3 `make openspec-validate` passes in strict mode

## Workflow follow-up

- Present the artifacts for review; run `/opsx:apply` only after the user
  approves the proposal and spec deltas.
- After merge: `/opsx:archive`, close issue #131, update the task journal.
