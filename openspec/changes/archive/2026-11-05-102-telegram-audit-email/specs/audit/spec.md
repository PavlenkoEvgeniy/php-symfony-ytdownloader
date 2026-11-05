# Spec Delta

## Purpose

The Audit Trail records security- and operation-relevant actions with their
actor, channel, target and outcome, so the administrator can tell who did what
and through which surface.

## ADDED Requirements

### Requirement: Telegram actions are attributed to the linked User

Audit Events for actions performed through the Telegram bot SHALL identify the
actor as the linked User: actor type `user`, the User's id, and the User's email
as the actor label. The channel SHALL remain Telegram. The event SHALL retain the
Telegram chat id for traceability.

This applies to the Download Request, the Download outcome, the Telegram Delivery
outcome and the Source deletion caused by a successful delivery.

#### Scenario: Telegram download request

- **WHEN** a linked user submits a download through the bot
- **THEN** the Download Request Audit Event names the user's email as the actor
- **AND** the event's channel is Telegram
- **AND** the event retains the Telegram chat id

#### Scenario: Telegram download outcome

- **WHEN** a Download Task created through the bot completes or fails
- **THEN** the corresponding Audit Event names the user's email as the actor
- **AND** the event's channel is Telegram

#### Scenario: Telegram delivery

- **WHEN** a delivered file is sent to the user and its Source is deleted
- **THEN** both the delivery Audit Event and the Source deletion Audit Event name the user's email as the actor
- **AND** the events' channel is Telegram

#### Scenario: Actor cannot be resolved

- **WHEN** the task has no linked User at the time the Audit Event is written
- **THEN** the event keeps the Telegram actor type with the chat id as the actor label

### Requirement: Recorded Audit Events are not rewritten

The service SHALL NOT rewrite Audit Events that were already recorded; the
attribution rule applies only to events written after the change.

#### Scenario: Historical Telegram events

- **WHEN** the change is deployed
- **THEN** previously recorded events keep their original actor details
