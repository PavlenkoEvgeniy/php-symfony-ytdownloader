# Spec Delta

## ADDED Requirements

### Requirement: The bot speaks the user's language

A linked user SHALL choose the language of bot messages with /language,
from ten languages: English, Russian, Spanish, Portuguese, German, French,
Turkish, Arabic, Hindi and Chinese. The bot SHALL answer in that language
from then on, English being the default and the fallback for anything not
translated. The choice SHALL survive restarts, and /language SHALL be
listed in the command menu.

#### Scenario: Choosing a language

- **WHEN** a linked user sends /language and picks a language
- **THEN** the choice is remembered for the user
- **AND** the reply confirming the choice is written in that language

#### Scenario: Later messages follow the choice

- **WHEN** a user who chose a language receives any bot message afterwards
- **THEN** the message is written in that language

#### Scenario: Default and fallback stay English

- **WHEN** a user never chose a language, or a message is not translated
- **THEN** the bot answers in English

#### Scenario: Language command is discoverable

- **WHEN** the command menu is registered or a user sends /help
- **THEN** /language is listed
