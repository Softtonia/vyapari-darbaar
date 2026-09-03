# Vyapari Darbaar — Production Checklist & Deployment Guide

This document outlines the mandatory operational standards, configuration requirements, and deployment sequence for running Vyapari Darbaar in a production environment.

---

## 1. Mandatory Production Environment Settings

In your production `.env` file, configure the following values:

```env
APP_NAME="Vyapari Darbaar"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.vyaparidarbaar.com

FRONTEND_ADMIN_URL=https://admin.vyaparidarbaar.com
FRONTEND_USER_URL=https://app.vyaparidarbaar.com
CORS_ALLOWED_ORIGINS=https://admin.vyaparidarbaar.com,https://app.vyaparidarbaar.com

SUPPORT_EMAIL=support@vyaparidarbaar.com

# Database (MySQL 8.4+)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vyapari_darbaar
DB_USERNAME=vyapari_prod_user
DB_PASSWORD="STRONG_PRODUCTION_DB_PASSWORD"

# Cache & Queue (Redis 5.0+)
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD="STRONG_REDIS_AUTH_PASSWORD"
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

# Email (Zoho SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.zoho.in
MAIL_PORT=465
MAIL_USERNAME=notifications@vyaparidarbaar.com
MAIL_PASSWORD="ROTATED_PRODUCTION_SMTP_PASSWORD"
MAIL_SCHEME=smtps
MAIL_FROM_ADDRESS=notifications@vyaparidarbaar.com
MAIL_FROM_NAME="${APP_NAME}"
```

> [!CAUTION]
> **SMTP Credential Rotation Gate**: Any development Zoho credentials must be manually revoked/rotated in Zoho before live production traffic is enabled.

---

## 2. Queue Supervision (Linux / Supervisor)

To process encrypted credential emails and notifications in production, configure **Supervisor** on Linux.

### Configuration File: `/etc/supervisor/conf.d/vyapari-worker.conf`

```ini
[program:vyapari-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vyapari-darbaar/artisan queue:work redis --queue=emails,default,low --tries=3 --backoff=10,30,60 --timeout=60 --sleep=3 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/vyapari-darbaar/storage/logs/worker.log
stopwaitsecs=3600
```

### Queue Priority
1. `emails` (Highest priority: credential provisioning and reset links)
2. `default` (Standard operational background jobs)
3. `low` (Batch tasks / analytics)

### Supervisor Commands
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vyapari-worker:*
```

---

## 3. Failed Jobs Management

When a job fails after all 3 retries with exponential backoff (`[10, 30, 60]` seconds), it is recorded in the `failed_jobs` table.

- **List failed jobs:**
  ```bash
  php artisan queue:failed
  ```
- **Retry a specific failed job:**
  ```bash
  php artisan queue:retry <id>
  ```
- **Retry all failed jobs:**
  ```bash
  php artisan queue:retry all
  ```
- **Forget/delete a specific failed job:**
  ```bash
  php artisan queue:forget <id>
  ```

---

## 4. Production Deployment Order

Follow this exact command sequence on production rollout:

```bash
# 1. Pull latest code
git pull origin main

# 2. Install production PHP dependencies (optimized autoloader, no dev)
composer install --no-dev --optimize-autoloader

# 3. Run additive database migrations
php artisan migrate --force

# 4. Clear old caches
php artisan optimize:clear

# 5. Cache configurations, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart queue workers gracefully
php artisan queue:restart
```

---

## 5. Security & Infrastructure Hardening

- [x] **Strict Model Isolation:** `EnsureAdmin` and `EnsureUser` prevent cross-model token escalation.
- [x] **Rate Limiting:** Dedicated Redis limits for login, password reset, user creation, credential resend, and authenticated API requests.
- [x] **Payload Encryption:** Queued credential and password reset notifications implement `ShouldBeEncrypted`.
- [x] **CORS:** Restricted to explicit `FRONTEND_ADMIN_URL` and `FRONTEND_USER_URL` with HTTPS in production.
- [x] **Server Security:**
  - Enforce HTTPS via Let's Encrypt / Cloudflare SSL.
  - Close external access to MySQL (port 3306) and Redis (port 6379) via firewall (UFW / AWS Security Groups).
  - Configure daily automated MySQL backups.
  - Enable log rotation for `/storage/logs/laravel.log`.
