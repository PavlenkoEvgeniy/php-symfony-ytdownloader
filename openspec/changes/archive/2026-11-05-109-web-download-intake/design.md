# Design

## Context

The web intake switch is a runtime setting, so it cannot be an environment
variable. The service already has a single funnel for new tasks,
`DownloadDispatcher`, which every surface uses; it persists a Download Task and
then dispatches the queue message. The switch must be checked before that persist
step so a rejected request leaves no task behind.

## Goals / Non-Goals

**Goals:**

- One enforcement point that covers both web surfaces and cannot be bypassed by
  a new entry point.
- Keep the Telegram surface untouched.
- Keep the Library independent of the switch.

**Non-Goals:**

- Cancelling or replaying existing tasks.
- Per-user switches; the setting is global.
- Caching the setting.

## Decisions

- Store the switch in a singleton `download_settings` row; a missing row means
  "enabled". The admin page at `/admin/settings` edits it and records an
  `admin.settings_updated` audit event.
- Enforce in `DownloadDispatcher` via a `DownloadIntakePolicy` that accepts
  `origin = telegram` and rejects other origins when the switch is off. Every
  current and future surface goes through the dispatcher, so the rule lives in
  one place.
- Map the rejection at the edges: the UI redirects to the Library with a warning;
  the API controllers translate the domain exception into 403 Problem Details.
- Read the setting on each request with no cache: one primary-key lookup is cheap
  and avoids a stale switch.

## Risks / Trade-offs

- A long-running worker never reads the switch, by design: a task accepted before
  the switch is allowed to finish.
- The global switch also applies to admins; re-enabling it is the way to use the
  web surfaces again.
- The UI redirect and the API response are two translations of one domain
  exception; a new surface must add its own translation or return the domain
  error.
