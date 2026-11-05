# Tasks

## 1. Attribution

- [x] 1.1 Let `AuditLogger` accept an explicit actor and resolve type, id and email from it
- [x] 1.2 Attribute `download.requested` to the User in `DownloadDispatcher`
- [x] 1.3 Attribute `download.completed`/`download.failed` to the task's User in `DownloadMessageHandler`
- [x] 1.4 Attribute `telegram.delivered`/`telegram.delivery_failed` and the delivery `source.deleted` to the User
- [x] 1.5 Keep the Telegram chat id in the metadata of all these events

## 2. Tests

- [x] 2.1 Audit logger uses the explicit actor over the token context
- [x] 2.2 Dispatcher audits the email with the Telegram actor type fallback
- [x] 2.3 Download handler audits the task's User and the fallback
- [x] 2.4 Delivery handler audits the User for delivery and source deletion

## 3. Docs

- [x] 3.1 Add the Audit Event, Actor and Channel glossary terms
- [x] 3.2 Record ADR 0012 about the audit actor identity

## 4. Verification

- [x] 4.1 Run `make lint`, `make test` and `make openspec-validate`
- [x] 4.2 Validate strictly and archive the change
