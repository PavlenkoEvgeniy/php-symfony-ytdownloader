# Design

## Context

`APP_VERSION` already flows into the web footer (`twig.yaml`) and the
health check. The bot command menu is registered by
`TelegramCommandsCommand` from a constant list. The deployment checks the
release tag out into the bind-mounted project directory, and the `.env`
file changes in every release (the version bump), so its mtime marks the
deployment.

## Goals / Non-Goals

**Goals:**
- `/info` shows version and deployment time; discoverable in menu and
  help.
- No new deploy-pipeline machinery.

**Non-Goals:**
- Deploy duration, commit hash or changelog; a deploy-timestamp file.

## Decisions

- **Deployment time = `.env` mtime**, formatted as UTC: the release
  checkout rewrites `.env` (version bump) at the deploy moment; the file
  lives in the bind mount, so its mtime is the checkout time. A missing
  file degrades to "unknown" instead of an error.
- **The version comes from the same `APP_VERSION` env** the footer uses —
  one source of truth.
- **The reply is English** like every other bot message; UTC is labelled
  explicitly so the timestamp is unambiguous across time zones.
- **Help and menu get the entry too**: a command nobody can discover is
  not added.

## Risks / Trade-offs

- A re-run of the deploy machinery for the *same tag* would not touch
  `.env` and the reported time would stay at the original checkout; the
  deploy is idempotent and such re-runs are rare, the value stays honest
  to "when this version arrived".
- Filesystem mtime can be altered (touched) by hand; this is a display
  value, not an audit trail.
