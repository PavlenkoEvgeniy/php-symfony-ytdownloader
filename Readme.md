# 🎬 YouTube, Rutube, VK Video Downloader  

*A lightweight service for downloading videos from YouTube, Rutube, Instagram, Ok and Vk*  

**🛠 Tech Stack**:  
- PHP 8 🐘  
- Symfony 7 🎼 
- EasyAdmin 4 🛠️
- Docker 🐳  
- PostgreSQL 🐘  
- yt-dlp ⚡  
- norkunas/youtube-dl-php 📦  
- botman/botman 🤖

## 📸 Preview  
<img src="docs/preview/1.jpg" alt="Login page" height="300"> <img src="docs/preview/2.jpg" alt="Index page" height="300"> <img src="docs/preview/3.jpg" alt="Downloads page" height="300"> <img src="docs/preview/4.jpg" alt="Admin dashboard" height="300"> <img src="docs/preview/5.jpg" alt="Admin menu" height="300">  

## ⚠️ Legal Disclaimer:
This program is for personal use only. Downloading copyrighted material without permission is against YouTube's terms of services. By using this program, you are solely responsible for any copyright violations. We are not responsible for people who attempt to use this program in any way that breaks YouTube's terms of services.



## 📋 Tested within:
1. 🐧 Ubuntu 22.04
2. 🐳 Docker 28.3.2
3. 📦 Docker-compose 1.29.2
4. ⚙️ GNU Make 4.3

## 🚀 Quick Start  

### ⚡ Run the Project:
1. **Environment**
   > 📝 **Note**: Create `.env.local` with DB config (host name must be `ytdownloader-pgsql`)
   ```yaml
   DATABASE_URL="postgresql://example_user_name:example_passwd12345@'ytdownloader-pgsql':5432/ytdownloader?serverVersion=16&charset=utf8"
   ```

1. **Initialize new application**:  
   ```bash
   sudo make init
   ```

2. **Restart application**:  
   ```bash
   sudo make restart
   ```

3. **Setup database (if needed)**:  
   ```bash
   sudo make db-setup
   ```

4. **Start queue worker (if needed)**:  
   ```bash
   sudo make supervisor-start
   ```

5. **Create admin by console command**:  
   ```bash
   sudo make docker-php
   php bin/console user:add <username> [password]
   ```

6. **Run tests**:  
   ```bash
   sudo make test
   ```

7. **List of all available 'make' commands**
   ```bash
   sudo make help
   ```

8. **Health check url**:  
   ```
   GET http://host.tld/health
   ```
9.  **Admin dashboard**:  
   ```
   GET http://host.tld/admin
   ```
10. **Telegram bot**:  
   - add enable true for telegram bot in .env.local file
   - add your bot token to .env.local file
   - add telegram host url to .env.local file
   - run the command to setup webhook:
   ```bash
   php bin/console telegram:hook
   ```
   - **Telegram bot commands**:
   ```
    /start - start bot
   
    https://youtube.com/video-url - download video by url
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
