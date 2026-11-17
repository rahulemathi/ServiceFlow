# ServiceFlow Production Operations & Reliability Guide

## Overview
This runbook provides deployment, operational maintenance, disaster recovery, and security guidelines for operating ServiceFlow in production.

---

## 1. System Requirements & Architecture
- **PHP**: 8.4+ with `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo_mysql`, `tokenizer`, `xml`, `bcmath`.
- **Database**: MySQL 8.0+ or MariaDB 10.11+ (or PostgreSQL / SQLite in testing).
- **Cache & Session**: Redis 7.0+ recommended for high-volume deployments.
- **Queue**: Redis or Database queue driver.
- **Storage**: AWS S3, Cloudflare R2, or local disk storage with private access controls.

---

## 2. Deployment Procedure

### Standard Zero-Downtime Deployment Steps
```bash
# 1. Put application into maintenance mode with bypass cookie
php artisan down --secret="deployment-bypass-token" --render="errors::503"

# 2. Pull latest code
git fetch origin main
git reset --hard origin/main

# 3. Install production PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Run database migrations safely
php artisan migrate --force

# 5. Clear and rebuild configuration, route, view, and event caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Restart queue workers and scheduled workers
php artisan queue:restart

# 7. Bring application out of maintenance mode
php artisan up
```

### Rollback Procedure
```bash
# 1. Activate maintenance mode
php artisan down

# 2. Revert code to previous release tag or git SHA
git checkout <PREVIOUS_COMMIT_OR_TAG>

# 3. Roll back migrations if necessary
php artisan migrate:rollback --step=1 --force

# 4. Rebuild caches and restart queues
composer install --no-dev --optimize-autoloader --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

# 5. Restore application
php artisan up
```

---

## 3. Background Workers & Task Scheduling

### 3.1 Task Scheduler
Add the standard Laravel cron job to the server's crontab (`crontab -e`):
```cron
* * * * * cd /var/www/serviceflow && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks include:
- `serviceflow:generate-maintenance-work-orders`: Daily preventive maintenance work order generation and contract expiration auditing.

### 3.2 Queue Workers (Supervisor Configuration)
Create `/etc/supervisor/conf.d/serviceflow-worker.conf`:
```ini
[program:serviceflow-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/serviceflow/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/serviceflow/storage/logs/worker.log
stopwaitsecs=3600
```

---

## 4. Health Checks & Monitoring

### Endpoint: `GET /health`
- **Path**: `/health`
- **Output format**: JSON
- **Success code**: `200 OK`
- **Failure code**: `503 Service Unavailable`
- **Response payload**:
  ```json
  {
    "status": "healthy",
    "timestamp": "2026-11-17T12:00:00Z",
    "environment": "production",
    "checks": {
      "database": { "status": "ok" },
      "storage": { "status": "ok" },
      "cache": { "status": "ok" }
    }
  }
  ```

Configure your uptime monitoring service (e.g. Better Uptime, Datadog, Pingdom, Cloudflare Health Checks) to poll `/health` every 60 seconds and alert if status code is not 200.

---

## 5. Backups & Restore Verification

### Verification Command
Run the built-in backup verification command to confirm schema integrity, essential tables, and storage availability:
```bash
php artisan serviceflow:verify-backup
```

### Automated Backup Recommendation
Use `spatie/laravel-backup` or cloud-managed snapshots (AWS RDS Automated Backups / Google Cloud SQL Backups) scheduled daily with 30-day retention.
Conduct a quarterly restore drill into a staging database and execute `serviceflow:verify-backup` to guarantee recovery readiness.

---

## 6. Multi-Tenancy Security Invariants
- **Platform Branding**: Only platform administrators (`hasPlatformRole(UserRole::PlatformAdmin)`) may alter global branding.
- **SMTP**: Platform-wide configuration only. No runtime `.env` editing. Passwords masked.
- **Demo Accounts**: Strictly prohibited in `production` and `staging` environments.
- **Tenant Isolation**: Every database query on operational resources is scoped by `organization_id` and permitted `branch_id`.
- **API Security**: Token revocation, rate limiting (60 req/min), and tenant membership verification on all `/api/v1` routes.
