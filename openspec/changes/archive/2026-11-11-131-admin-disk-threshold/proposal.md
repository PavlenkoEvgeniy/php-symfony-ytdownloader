# Proposal

## Why

The minimum free-disk-space threshold that rejects new downloads is fixed in
deployment configuration (`MIN_FREE_DISK_MB`, 5 GB), so an administrator cannot
adjust it at runtime: changing it requires editing environment files and
restarting the stack, even though the value is an operational knob
([issue #131](https://github.com/PavlenkoEvgeniy/php-symfony-ytdownloader/issues/131)).

## What Changes

- The minimum free space becomes an admin-managed setting, edited on the
  existing Admin Panel Settings page next to the web intake switch and the
  progress interval.
- The setting is an integer in megabytes; `0` turns the disk pressure guard
  off, and values are capped at 1048576 MB (1 TiB) so the byte conversion
  cannot overflow.
- An empty value means "follow the deployment default": the environment
  variable stays the factory default for fresh installs and for administrators
  who never touch the setting.
- The Settings page shows the current free space in the download volume,
  whether it is below the effective minimum, and the effective minimum with
  its origin (deployment default or administrator-set).
- A threshold change takes effect immediately for new requests; Download Tasks
  that were already accepted are not affected (unchanged behavior).
- Documentation follows: README, the disk-space runbook, and a correction note
  in ADR-0010.

## Capabilities

### New Capabilities

- (none)

### Modified Capabilities

- `download-intake`: the "Disk pressure guard" requirement gains
  admin-configurable threshold semantics — runtime change applies to subsequent
  requests, `0` disables the guard, and the default stays the deployment
  configuration (5 GB).
- `admin-panel`: new requirement for the disk state block on the Settings page
  (current free space, low-space indicator, threshold field with its origin).

## Impact

- **Code**: `DownloadSettings` entity + migration (new nullable column),
  `DownloadSettingsForm`, admin `SettingsController` and its template,
  `DiskSpacePolicy` (reads the threshold from the settings repository per call,
  no caching), `DiskSpacePolicyInterface` unchanged.
- **Config**: `MIN_FREE_DISK_MB` keeps its role as the factory default; no
  change to `.env` handling or the env generator.
- **Docs**: `README.md`, `docs/runbook/disk-space.md`, note in
  `docs/adr/0010-reject-downloads-under-disk-pressure.md`.
- **Tests**: unit tests for threshold resolution (DB value, `NULL` → env,
  `0` disables), functional test for saving the Settings form; existing
  disk-pressure tests keep passing unchanged.
- **Surfaces**: behavior visible on the Admin Panel Settings page; rejection
  behavior on UI / REST API v1/v2 / Telegram is unchanged apart from the
  configurable threshold.
