# Proposal

## Why

The Library lists files but clicking a file name does nothing, so a user who
wants the details of a file has to find the small view icon. The name is the
natural link to the Source details.

GitHub issue: #106.

## What Changes

- The file name in the Library list links to the Source details page.

## Capabilities

### Modified Capabilities

- `library`: the file name opens the Source details.

## Impact

- The Library list template and its link styles; no data change.
- Out of scope: changing the details page content or the download action.
