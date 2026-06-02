# Media Downloader - Production Ready

A complete, production-ready website for downloading audio/video from URLs or playlists (YouTube, Vimeo, SoundCloud, TikTok, etc.).

## Stack
- **Backend**: Laravel 11 with Reverb (WebSockets), Redis, MySQL
- **Frontend**: React 18 + Redux Toolkit + RTK Query + Tailwind CSS
- **Real-time**: Laravel Reverb (Pusher protocol)
- **Storage**: Local/MinIO/S3 compatible

## Features
- ✅ Auto-registration with guest_token (no email/password required)
- ✅ Download single URLs or entire playlists
- ✅ Real-time progress via WebSockets
- ✅ Scheduled downloads
- ✅ Social sharing with short links
- ✅ Browser extension API
- ✅ Monetization ready (premium tier support)
- ✅ Rate limiting per user
- ✅ PWA support
- ✅ Dark/Light themes

## Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8+
- Redis
- yt-dlp installed on server

### Backend Setup

```bash
cd backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=media_downloader
DB_USERNAME=root
DB_PASSWORD=

# Run migrations
php artisan migrate

# Start queue worker
php artisan queue:work --daemon

# Start Reverb server
php artisan reverb:start

# Start local server
php artisan serve
```

### Frontend Setup

```bash
cd frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Start development server
npm run dev

# Build for production
npm run build
```

### Docker Setup (Recommended)

```bash
# Start all services
docker-compose up -d

# Run migrations
docker-compose exec backend php artisan migrate

# Start queue worker
docker-compose exec backend php artisan queue:work --daemon

# View logs
docker-compose logs -f
```

## Directory Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Middleware/
│   ├── Models/
│   ├── Jobs/
│   ├── Events/
│   ├── Services/
│   └── DTOs/
├── database/migrations/
├── routes/
├── config/
└── tests/

frontend/
├── src/
│   ├── app/
│   ├── features/
│   ├── pages/
│   ├── components/
│   └── utils/
├── public/
└── .env.example
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/register-guest | Register guest and get token |
| POST | /api/download | Submit download request |
| GET | /api/downloads | List all downloads |
| GET | /api/download/{id}/status | Get download status |
| GET | /api/download/{id}/file | Download file (signed URL) |
| DELETE | /api/download/{id} | Delete download |
| POST | /api/download/batch | Batch download (up to 10) |
| GET | /api/downloads/stats | Get statistics |
| GET | /api/share/{token} | Public share page |
| POST | /api/preferences | Update preferences |
| POST | /api/schedule | Schedule download |
| POST | /api/extension/submit | Extension submit URL |

## Environment Variables

### Backend (.env)
```
APP_NAME="Media Downloader"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=media_downloader
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

BROADCAST_DRIVER=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

PUSHER_APP_ID=${REVERB_APP_ID}
PUSHER_APP_KEY=${REVERB_APP_KEY}
PUSHER_APP_SECRET=${REVERB_APP_SECRET}
PUSHER_HOST=${REVERB_HOST}
PUSHER_PORT=${REVERB_PORT}
PUSHER_SCHEME=${REVERB_SCHEME}

FILESYSTEM_DISK=local
# For S3/MinIO:
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=us-east-1
# AWS_BUCKET=media-downloads
# AWS_ENDPOINT=

YT_DLP_PATH=/usr/local/bin/yt-dlp
DOWNLOAD_PATH=/var/www/storage/app/downloads
MAX_DOWNLOADS_FREE=50
MAX_DOWNLOADS_PREMIUM=100
FILE_RETENTION_DAYS_FREE=7
FILE_RETENTION_DAYS_PREMIUM=30
```

### Frontend (.env)
```
VITE_API_URL=http://localhost:8000/api
VITE_REVERB_APP_KEY=your-reverb-app-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

## Rate Limits

| Tier | Pending Downloads | Daily Limit | File Retention |
|------|------------------|-------------|----------------|
| Free | 10 | 50 | 7 days |
| Premium | 50 | 100 | 30 days |

## Testing

```bash
# Backend tests
cd backend
php artisan test

# Frontend tests
cd frontend
npm test
```

## Browser Extension

The API supports a companion browser extension. Users can generate an API token from Settings to authenticate extension requests.

## Monetization

Premium features are controlled by the `is_premium` flag on users. Middleware checks this flag and returns 402 Payment Required for premium-only endpoints.

## Cleanup

Daily cleanup command removes old files:
```bash
php artisan downloads:cleanup
```

Add to crontab:
```
0 0 * * * cd /path/to/backend && php artisan downloads:cleanup
```

## License

MIT
