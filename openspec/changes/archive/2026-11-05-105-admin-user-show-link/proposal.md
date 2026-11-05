# Proposal

## Why

Clicking a user in the admin Users list opens the edit form. The most common
reason to open a user is to look at their state, so the click should open the
read-only user card; editing should stay an explicit action.

GitHub issue: #105.

## What Changes

- Clicking a row in the Users list opens the user card (detail view) instead of
  the edit form.
- The card action remains available in the row actions and the edit action is
  still reachable from the card.

## Capabilities

### Modified Capabilities

- `admin-panel`: the Users list row opens the user card.

## Impact

- The Users CRUD only; no data change.
- Out of scope: changing the default action of the other admin lists.
