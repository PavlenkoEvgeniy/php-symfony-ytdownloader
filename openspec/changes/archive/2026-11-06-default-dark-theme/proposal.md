# Proposal

## Why

The interface currently defaults to the light theme (or follows the system theme
in auto mode). The product should open in the dark theme by default, while still
respecting a preference the user has already saved.

## What Changes

- A visitor without a saved theme preference sees the dark theme on the web UI.
- A saved preference (dark, light or auto) keeps taking precedence.
- The Admin Panel also starts in the dark color scheme when the administrator has
  not chosen another one.

## Capabilities

### New Capabilities

- `theme`: the default appearance of the web UI and the Admin Panel, and how a
  saved preference overrides it.

### Modified Capabilities

_None._

## Impact

- `templates/base.html.twig` (server-rendered dark fallback and the default in the
  theme script), `DashboardController` (EasyAdmin default color scheme), and the
  existing theme tests.
- Out of scope: adding new theme choices, changing the theme page layout, and
  changing how a preference is persisted.
