# Design

## Context

The web UI applies the theme in an inline script before the stylesheets load. It
reads `ytdownloader-theme` from localStorage and falls back to `auto`. The Admin
Panel uses EasyAdmin's color scheme switcher and its own persisted preference.

## Goals / Non-Goals

**Goals:**

- A fresh browser gets the dark theme without a flash of light.
- Existing saved preferences keep working.
- The Admin Panel matches the dark default.

**Non-Goals:**

- Changing the theme options or the theme page.
- Storing the preference server-side.
- Changing the CSS palettes.

## Decisions

- **Change the script fallback to `dark`.** `readMode()` returns the stored value
  when valid and `dark` otherwise (including when localStorage is unavailable).
  This keeps the existing preference semantics and makes the dark theme the
  default for new visitors.
- **Server-render `data-bs-theme="dark"` on `<html>`.** The inline script still
  overrides it from localStorage before the first paint, but the server-rendered
  value gives a correct first paint when JavaScript is disabled.
- **EasyAdmin default color scheme.** `Dashboard::setDefaultColorScheme(
  ColorScheme::DARK)` sets the admin default; EasyAdmin's own switcher preference
  still overrides it.
- **Tests.** The existing base-layout test asserts the dark fallback; no new
  storage mechanism is introduced.

## Risks / Trade-offs

- A visitor who previously used the default (which was `auto`) keeps the auto
  value in localStorage, so the change applies to visitors without a stored
  preference. That is the intended meaning of "default".
- The admin default is a browser-side EasyAdmin preference; an administrator who
  explicitly chose light keeps light.
