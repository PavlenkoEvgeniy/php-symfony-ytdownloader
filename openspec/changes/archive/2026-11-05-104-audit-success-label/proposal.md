# Proposal

## Why

The Audit Event list shows the outcome of every action as a toggle switch. A
switch implies the value can be changed, but the audit log is read-only; the
administrator expects to read the outcome at a glance.

GitHub issue: #104.

## What Changes

- The Audit Event list and card show the outcome as Yes/No text instead of a
  switch.

## Capabilities

### Modified Capabilities

- `admin-panel`: the audit list shows the outcome as text.

## Impact

- The Audit Event admin field only; no data or behavior change.
- Out of scope: changing how the outcome is stored or filtered.
