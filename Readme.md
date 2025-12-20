# 🎬 Download video and audio from YouTube, Instagram, Telegram, TikTok and others 

*A lightweight service for downloading video and audio from YouTube, Instagram, Telegram, TikTok and others*

**🛠 Tech Stack**:

- PHP 8 🐘
- Symfony 7 🎼
- EasyAdmin 4 🛠️
- Docker 🐳
- PostgreSQL 🐘
- Redis 🚀
- RabbitMQ 🐇
- yt-dlp ⚡
- norkunas/youtube-dl-php 📦
- botman/botman 🤖

## 📸 Preview

<img src="docs/preview/1.jpg" alt="Login page" height="300"> <img src="docs/preview/2.jpg" alt="Index page" height="300"> <img src="docs/preview/3.jpg" alt="Downloads page" height="300"> <img src="docs/preview/4.jpg" alt="Admin dashboard" height="300"> <img src="docs/preview/5.jpg" alt="Admin menu" height="300"> <img src="docs/preview/6.jpg" alt="Admin menu" height="300">

## ⚠️ Legal Disclaimer:

This program is for personal use only. Downloading copyrighted material without
permission is against YouTube's terms of services. By using this program, you
are solely responsible for any copyright violations. We are not responsible for
people who attempt to use this program in any way that breaks YouTube's terms of
services.

## 📋 Tested within:

1. 🐧 Ubuntu 22.04
2. 🐳 Docker 28.5.2
3. 📦 Docker compose v2.38.1
4. ⚙️ GNU Make 4.3

## 🚀 Quick Start

### ⚡ Run the Project:

1. **Environment**
   > 📝 **Note**: Create `.env.local` with DB config (host name must be
   `ytdownloader-pgsql`)
   ```yaml
   DATABASE_URL="postgresql://example_user_name:example_passwd12345@'ytdownloader-pgsql':5432/ytdownloader?serverVersion=16&charset=utf8"
   REDIS="redis://:example_passwd12345@ytdownloader-redis:6379"
   RABBITMQ_DSN="amqp://user:password@rabbitmq:5672/%2f"
   ```

2. **Docker environment**:  
   Copy docker .env from .env.example and update credentials for docker
   containers
    ```bash
    cp docker/.env.example docker/.env
    ```

3. **Initialize new application**:
   ```bash
   make init
   ```

4. **Restart application**:
   ```bash
   make restart
   ```

5. **Stop application**:
   ```bash
   make stop
   ```

6. **Setup database (if needed)**:
   ```bash
   sudo make db-setup
   ```

7. **Start queue worker (if needed)**:
   ```bash
   make supervisor-start
   ```

8. **Create admin user by console command**:
   ```bash
   make docker-php
   php bin/console app:user-add <username> [password]
   ```

9. **Run tests**:
   ```bash
   make test
   ```

10. **List of all available 'make' commands**:
    ```bash
    make help
    ```

11. **Health check url**:
    ```
    GET http://host.tld/health
    ```
12. **Admin dashboard**:
    ```
    GET http://host.tld/admin
    ```
13. **Telegram bot**:
    - add enable true for telegram bot in .env.local file
    - add your bot token to .env.local file
    - add telegram host url to .env.local file
    - run the command to setup webhook:
    ```bash
    make docker-php
    php bin/console app:telegram:hook
    ```
    - for unhook run command:
    ```bash
    make docker-php
    php bin/console app:telegram:unhook
    ```
14. **Telegram bot commands**:
    ```
    /start - start bot
    ```

## 📝 Todo Roadmap

✅ ~~Background video downloads (queues)~~  
✅ ~~Download status notifications~~  
✅ ~~Playlist special characters fix~~  
✅ ~~Tests coverage~~  
✅ ~~Refactor to services~~  
✅ ~~Health check endpoint~~  
🔳 YouTube cache optimization (avoid bot detection)  
✅ ~~Download statistics counter~~  
🔳 REST API implementation  
✅ ~~Telegram bot integration~~  
✅ ~~Setup automation script~~  
✅ ~~Admin dashboard~~  
