# Proposal

## Why

The Library lists each file as a row with small action icons. Only the file name
and the view icon open the Source details, so a user who clicks elsewhere in the
row gets no reaction. The row itself is the natural target.

This extends the behavior requested in #106 (the file name links to the details
page), which is already shipped.

## What Changes

- Clicking anywhere in a Library file row opens the Source details page.
- The Download, Edit and Delete actions stay clickable and do not navigate.

## Capabilities

### Modified Capabilities

- `library`: the whole file row opens the Source details.

## Impact

- The Library list markup and stylesheet; no data change.
- Out of scope: turning the table into cards on mobile and changing the actions.
