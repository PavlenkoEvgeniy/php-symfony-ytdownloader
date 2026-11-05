# Proposal

## Why

The Source details page shows a file but offers no way to delete it; a user has
to go back to the Library list and find the delete action there. The details
page is where a user decides to remove a file.

GitHub issue: #107.

## What Changes

- The Source details page gets a Delete button next to Download.
- Deleting asks for confirmation, like the existing delete actions.

## Capabilities

### Modified Capabilities

- `library`: the Source details page offers a confirmed delete action.

## Impact

- The details template and the shared delete form; no data change.
- Out of scope: new deletion rules; the existing controller, CSRF check and file
  removal are reused.
