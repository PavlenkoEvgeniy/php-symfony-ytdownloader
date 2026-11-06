# Spec Delta

## ADDED Requirements

### Requirement: Dark theme is the default

The web UI SHALL use the dark theme when no theme preference has been saved.

#### Scenario: First visit without a saved preference

- **WHEN** a visitor opens the web UI with no saved theme preference
- **THEN** the interface is rendered in the dark theme

#### Scenario: Saved preference wins

- **WHEN** a visitor has saved the light or auto theme
- **THEN** that saved preference is used instead of the dark default

### Requirement: Admin Panel starts dark

The Admin Panel SHALL use the dark color scheme when the administrator has not
selected another scheme.

#### Scenario: First admin visit

- **WHEN** an administrator opens the Admin Panel without a selected color scheme
- **THEN** the Admin Panel uses the dark color scheme

#### Scenario: Administrator selects another scheme

- **WHEN** an administrator selects the light or auto scheme in the Admin Panel
- **THEN** that selection is used for subsequent admin pages
