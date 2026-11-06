# Tasks

## 1. Telegram column

- [x] 1.1 Add the read-only `telegramLinked` boolean field to the Users index
- [x] 1.2 Verify the Yes/No rendering through the existing `isTelegramLinked()` getter

## 2. Enabled confirmation

- [x] 2.1 Override the EasyAdmin boolean field template with an optional `confirm` option
- [x] 2.2 Set the confirmation message on the Enabled field
- [x] 2.3 Add a confirmation and remove the left offset on the Settings web intake switch
- [x] 2.4 Add en/ru translations

## 3. Tests and verification

- [x] 3.1 Test the Telegram Yes/No column for linked and unlinked users
- [x] 3.2 Test that the Enabled toggle carries the confirm handler
- [x] 3.3 Run `make lint`, `make test`, `make openspec-validate`

## 4. OpenSpec

- [x] 4.1 Sync the spec and archive the change
