# Vyapari Darbaar

Enterprise REST API backend for the **Vyapari Darbaar** marketplace platform, built on Laravel 12.

---

## 1. Verified Stack & Infrastructure

- **Operating System / Environment:** Windows (Laragon) / Linux production
- **PHP:** 8.3.30
- **Framework:** Laravel 12.x
- **Database:** MySQL 8.4.3
- **Authentication:** Laravel Sanctum 4.x (Polymorphic token isolation)
- **Caching & Locking:** Redis 5.0.14.1 via `phpredis` 6.3.0 (`CACHE_STORE=redis`)
- **Background Queue:** Redis `emails` queue (`QUEUE_CONNECTION=redis`)
- **Email Delivery:** Zoho SMTP (`smtp.zoho.in:465` SSL / TLS)

---

## 2. Local Setup Guide (Laragon)

### Prerequisites
Ensure Laragon services (MySQL and Redis) are running.

### Installation Steps

```powershell
# 1. Install Composer dependencies
composer install

# 2. Setup environment configuration
copy .env.example .env

# 3. Generate application encryption key
php artisan key:generate

# 4. Configure database and Redis settings in .env
# DB_DATABASE=vyapari_darbaar
# REDIS_CLIENT=phpredis

# 5. Run database migrations
php artisan migrate

# 6. Seed initial administrator and email templates
php artisan db:seed

# 7. Start local development server
php artisan serve

# 8. Start local queue worker for processing encrypted emails
php artisan queue:work redis --queue=emails,default --tries=3
```

---

## 3. Running the Automated Test Suite

Run the full automated test suite containing 108 tests and 556 assertions:

```powershell
php artisan test
```

---

## 4. Documentation & API Reference

- **[API Reference](docs/API.md):** Detailed endpoint specifications, request payloads, response structures, status codes, and rate limits.
- **[Postman Collection](docs/postman/Vyapari-Darbaar.postman_collection.json):** Pre-configured Postman collection covering all 21 REST API endpoints.
- **[Postman Environment](docs/postman/Vyapari-Darbaar.postman_environment.json):** Environment variables for local and staging testing.
- **[Production Checklist](docs/PRODUCTION_CHECKLIST.md):** Linux Supervisor queue setup, deployment command sequence, failed job management, and security checklist.

---

## 5. Security & Architectural Standards

1. **Polymorphic Token Isolation:** `EnsureAdmin` and `EnsureUser` middleware guarantee that Administrator tokens cannot access User endpoints and vice versa.
2. **First-Login Password Enforcement:** Provisioned users with `must_change_password=true` must update their temporary password before accessing business routes (`EnsurePasswordChanged`).
3. **Encrypted Queue Payloads:** Sensitive credential and password reset notifications implement `ShouldBeEncrypted` and `ShouldQueue` on the Redis `emails` queue.
4. **Deterministic Rate Limiting:** Namespaced Redis rate limiters prevent brute force attacks on authentication, user provisioning, and credential resending.
5. **Zero File Attack Surface:** No file upload or storage endpoints are exposed.
