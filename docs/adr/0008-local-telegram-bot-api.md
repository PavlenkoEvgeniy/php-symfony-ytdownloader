# Telegram files go through a local Bot API server

The cloud Bot API rejects bot uploads larger than 50 MB, while a typical 1080p
video is larger. The bot must send the file to the requester and then delete it,
so sending a public URL is not an option.

We run the official `telegram-bot-api` server (image
`aiogram/telegram-bot-api`) in the production compose file with `--local`,
which raises the upload limit to 2000 MB. The application sends all Bot API
calls to `http://telegram-bot-api:8081` inside the Docker network. The public
webhook stays on the cloud API, because Telegram delivers updates there and the
local server is not used for receiving. `TELEGRAM_API_ID` and
`TELEGRAM_API_HASH` come from GitHub secrets; the bot token stays in the server
environment.

## Considered Options

- **Cloud Bot API only** — the 50 MB upload limit makes most videos fail.
- **Send the file by URL** — Telegram fetches it itself, but the file must stay
  publicly reachable and would not be deleted after delivery.
- **Local Bot API** — chosen; adds a container and API credentials, but keeps
  the file private and supports uploads up to 2 GB.

## Consequences

- The local Bot API service must be running for bot replies and delivery; if it
  is down, downloads still work but the bot is silent.
- The API ID/hash are production secrets; the bot token travels only over the
  private Docker network.
- The webhook remains cloud-hosted; the local server is outbound-only.
- The `telegram-bot-api-data` volume persists between restarts.
