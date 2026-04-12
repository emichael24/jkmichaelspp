# JK Michaels Professional Mastery Platform — Deployment & DevOps

## Document Control

| Field | Value |
|---|---|
| Version | 1.0 |
| Date | February 26, 2026 |

---

## 1. Environments

| Environment | Purpose | URL | Branch |
|---|---|---|---|
| **Local** | Development | `http://localhost:8000` | feature/* |
| **Staging** | QA + client review | `https://staging.jkmichaels.com` | develop |
| **Production** | Live users | `https://app.jkmichaels.com` | main |

---

## 2. Infrastructure Architecture

```
                    ┌──────────────┐
                    │  Cloudflare   │
                    │  CDN + DNS    │
                    │  + WAF + DDoS │
                    └──────┬───────┘
                           │
                    ┌──────▼───────┐
                    │  Load        │
                    │  Balancer    │
                    │  (Nginx)     │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
       ┌──────▼──┐  ┌──────▼──┐  ┌──────▼──┐
       │  App    │  │  App    │  │  Queue   │
       │  Server │  │  Server │  │  Worker  │
       │  (PHP)  │  │  (PHP)  │  │  Horizon │
       └────┬────┘  └────┬────┘  └────┬────┘
            │            │            │
            └────────────┼────────────┘
                         │
         ┌───────────────┼───────────────┐
         │               │               │
    ┌────▼────┐    ┌─────▼─────┐   ┌─────▼─────┐
    │  MySQL  │    │   Redis   │   │    S3      │
    │  8.0    │    │  Cache +  │   │  Storage   │
    │  Primary│    │  Queue    │   │  (Media)   │
    │  +Replica│   └───────────┘   └───────────┘
    └─────────┘
         │
    ┌────▼────┐    ┌───────────┐
    │  MySQL  │    │Meilisearch│
    │  Replica│    │  Search   │
    │  (read) │    └───────────┘
    └─────────┘
```

---

## 3. Hosting Recommendation

### Option A: DigitalOcean + Laravel Forge (Recommended for Start)

| Resource | Spec | Monthly Cost (est.) |
|---|---|---|
| App Server (x2) | 4 vCPU / 8 GB RAM | $48 × 2 = $96 |
| MySQL (Managed) | 2 vCPU / 4 GB / 60 GB SSD | $60 |
| Redis (Managed) | 1 GB | $15 |
| Spaces (S3) | 250 GB + CDN | $5 + bandwidth |
| Laravel Forge | Server management | $12 |
| **Total** | | **~$190/month** |

### Option B: AWS (For Scale-out)

| Resource | Spec |
|---|---|
| EC2 (App) | t3.medium × 2 (behind ALB) |
| RDS MySQL | db.t3.medium (Multi-AZ) |
| ElastiCache Redis | cache.t3.micro |
| S3 + CloudFront | Media storage + CDN |
| SES | Transactional email |

Estimated: **$250-400/month** depending on traffic.

### Recommendation
Start with **DigitalOcean + Forge** for simplicity and cost. Migrate to AWS when scaling past 10,000 active users or when uptime SLA > 99.9% is required.

---

## 4. Server Configuration

### 4.1 PHP Configuration
```ini
# php.ini overrides
memory_limit = 256M
upload_max_filesize = 100M
post_max_size = 110M
max_execution_time = 120
max_input_vars = 5000
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0  # Production only
```

### 4.2 Nginx Configuration (Key Sections)
```nginx
server {
    listen 443 ssl http2;
    server_name app.jkmichaels.com;
    root /var/www/jkmichaels/public;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';";

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;
    gzip_min_length 1000;

    # Static file caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|webp)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Client max upload size
    client_max_body_size 110M;

    # Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 4.3 MySQL Configuration
```ini
[mysqld]
innodb_buffer_pool_size = 2G           # ~70% of available RAM for DB server
innodb_log_file_size = 256M
innodb_flush_method = O_DIRECT
innodb_flush_log_at_trx_commit = 2     # Balanced durability/performance
max_connections = 200
query_cache_size = 0                    # Disabled in MySQL 8
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

### 4.4 Redis Configuration
```
maxmemory 512mb
maxmemory-policy allkeys-lru
```

---

## 5. CI/CD Pipeline (GitHub Actions)

### 5.1 Pipeline Workflow

```
Push to feature/*  →  Run Tests + Lint
PR to develop      →  Run Tests + Lint + Build
Merge to develop   →  Deploy to Staging (auto)
Merge to main      →  Deploy to Production (manual approval)
```

### 5.2 GitHub Actions Config

```yaml
# .github/workflows/ci.yml
name: CI/CD Pipeline

on:
  push:
    branches: [develop, main]
  pull_request:
    branches: [develop, main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: testing
          MYSQL_DATABASE: jkm_test
        ports: ['3306:3306']
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
      redis:
        image: redis:7
        ports: ['6379:6379']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo, mysql, redis, gd, zip
          coverage: xdebug

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install PHP dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Install Node dependencies
        run: npm ci

      - name: Build frontend
        run: npm run build

      - name: Run PHP tests
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: jkm_test
          DB_USERNAME: root
          DB_PASSWORD: testing
          REDIS_HOST: 127.0.0.1
        run: php artisan test --coverage --min=70

      - name: Run frontend tests
        run: npm run test

      - name: Run linting
        run: |
          ./vendor/bin/pint --test
          npm run lint

  deploy-staging:
    needs: test
    if: github.ref == 'refs/heads/develop'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to Staging
        uses: appleboy/ssh-action@v2
        with:
          host: ${{ secrets.STAGING_HOST }}
          username: ${{ secrets.STAGING_USER }}
          key: ${{ secrets.STAGING_SSH_KEY }}
          script: |
            cd /var/www/jkmichaels
            git pull origin develop
            composer install --no-interaction --optimize-autoloader --no-dev
            npm ci && npm run build
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart
            php artisan horizon:terminate

  deploy-production:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment: production  # Requires manual approval
    steps:
      - name: Deploy to Production
        uses: appleboy/ssh-action@v2
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/jkmichaels
            php artisan down --retry=60
            git pull origin main
            composer install --no-interaction --optimize-autoloader --no-dev
            npm ci && npm run build
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart
            php artisan horizon:terminate
            php artisan up
```

---

## 6. Monitoring & Observability

### 6.1 Application Monitoring

| Tool | Purpose | Setup |
|---|---|---|
| **Laravel Telescope** | Debug queries, requests, exceptions, jobs | Install locally + staging only |
| **Laravel Horizon** | Queue monitoring dashboard | `/horizon` (admin protected) |
| **Sentry** | Error tracking + alerting | Laravel Sentry SDK |
| **DigitalOcean Monitoring** | Server metrics (CPU, RAM, disk, network) | Built-in with droplets |

### 6.2 Uptime Monitoring
- **UptimeRobot** or **Better Uptime** (free tier)
- Check `https://app.jkmichaels.com/health` every 1 min
- Alert via email + Slack/Teams

### 6.3 Health Check Endpoint
```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => DB::connection()->getPdo() ? 'ok' : 'error',
        'redis' => Cache::store('redis')->get('health-check') !== null || Cache::store('redis')->put('health-check', true, 10) ? 'ok' : 'error',
        'storage' => Storage::disk('s3')->exists('.health') || Storage::disk('s3')->put('.health', 'ok') ? 'ok' : 'error',
        'timestamp' => now()->toISOString(),
    ]);
});
```

### 6.4 Mobile App Monitoring (Capacitor)
- **Firebase Crashlytics** for crash reporting
- **Firebase Performance** for app start time, network latency
- **Firebase Analytics** for user behavior (optional)

### 6.5 Alerting Rules

| Alert | Condition | Notification |
|---|---|---|
| App down | Health check fails 2x consecutive | Email + Slack |
| High error rate | > 10 errors/min in Sentry | Email + Slack |
| Slow queries | Queries > 5s logged | Daily digest |
| Disk space | > 80% used | Email |
| Queue backup | > 1000 pending jobs | Email + Slack |
| CPU sustained | > 80% for 10 min | Email |
| Failed payments | > 5 failed in 1 hour | Email + Slack |

---

## 7. Backup Strategy

### 7.1 Database Backups
- **Automated daily backups** via managed MySQL (DigitalOcean/AWS)
- Retention: 7 daily + 4 weekly + 3 monthly
- Point-in-time recovery enabled
- Monthly restore test (documented)

### 7.2 File Storage Backups
- S3 versioning enabled
- Cross-region replication for critical data (certificates, submissions)
- 90-day lifecycle for deleted objects

### 7.3 Application Backups
- Git repository is the source of truth
- Environment files stored in encrypted vault (1Password / AWS Secrets Manager)
- Server configuration documented + scripted (Laravel Forge / Ansible)

---

## 8. Security Hardening

### 8.1 Server Level
- SSH key-only access (no password auth)
- Fail2ban configured
- UFW firewall: only 22, 80, 443 open
- Automatic security updates enabled
- Non-root SSH user for deployments

### 8.2 Application Level
- Laravel security best practices (CSRF, XSS, SQL injection prevention)
- Rate limiting on all public endpoints
- Honeypot on registration form
- Content Security Policy headers
- Subresource Integrity for CDN assets
- API token expiration: 7 days (refresh on use)

### 8.3 Data Level
- Database encrypted at rest
- TLS 1.3 for all connections
- Sensitive fields (payment references) encrypted in application layer
- PII access logged in audit trail
- Regular dependency vulnerability scans (`composer audit`, `npm audit`)

---

## 9. Scaling Plan

### Phase 1: < 5,000 users
Single app server + single DB. Current architecture sufficient.

### Phase 2: 5,000 - 20,000 users
- Add read replica for MySQL
- Separate queue worker server
- CDN for all static assets + media
- Redis cluster for caching

### Phase 3: 20,000 - 100,000 users
- Horizontal app server scaling (2-4 servers behind load balancer)
- Database sharding or migration to managed service
- Dedicated search server (Meilisearch)
- Media served entirely via CDN
- Consider serverless for PDF/certificate generation

### Phase 4: 100,000+ users
- Evaluate microservices extraction for heaviest domains (Assessment Engine, Analytics)
- Database partitioning
- Multi-region deployment
- Dedicated DevOps team

---

## 10. Mobile App Deployment

### 10.1 Android (Google Play Store)
- Build via Capacitor: `npx cap build android`
- Signed with release keystore
- Upload AAB to Google Play Console
- Target: internal testing → closed beta → production
- Review time: typically 1-3 days

### 10.2 iOS (Apple App Store)
- Build via Capacitor: `npx cap build ios`
- Signed with Apple distribution certificate
- Upload via Xcode / Transporter
- TestFlight for beta testing
- App Store review: typically 1-7 days

### 10.3 PWA Distribution
- Automatically available at `https://app.jkmichaels.com`
- "Add to Home Screen" prompt after 2nd visit
- No store submission required

### 10.4 Version Management
- App version follows SemVer: `MAJOR.MINOR.PATCH`
- Web/PWA: updates immediately on deploy
- Mobile: critical fixes via app store update; content updates via API
- Force-update mechanism: API returns `min_app_version`, client shows "Update required" if below

---

## 11. Environment Variables (.env Reference)

```env
# Application
APP_NAME="JK Michaels Learning"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://app.jkmichaels.com

# Database
DB_CONNECTION=mysql
DB_HOST=db-host.com
DB_PORT=3306
DB_DATABASE=jkmichaels
DB_USERNAME=jkm_user
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis-host.com
REDIS_PASSWORD=secret
REDIS_PORT=6379

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=hello@jkmichaels.com
MAIL_FROM_NAME="JK Michaels Learning"

# File Storage
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=xxxxx
AWS_SECRET_ACCESS_KEY=xxxxx
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=jkm-learning-media

# Search
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=xxxxx

# Payments
PAYSTACK_PUBLIC_KEY=pk_live_xxx
PAYSTACK_SECRET_KEY=sk_live_xxx
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK-xxx
FLUTTERWAVE_SECRET_KEY=FLWSECK-xxx
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx

# Push Notifications (VAPID)
VAPID_PUBLIC_KEY=xxxxx
VAPID_PRIVATE_KEY=xxxxx

# Broadcasting (Reverb)
BROADCAST_DRIVER=reverb
REVERB_APP_ID=xxxxx
REVERB_APP_KEY=xxxxx
REVERB_APP_SECRET=xxxxx

# Sentry
SENTRY_LARAVEL_DSN=https://xxxxx@sentry.io/xxxxx

# Feature Flags
FEATURE_AI_COACH=false
FEATURE_SIMULATIONS=true
FEATURE_INTERNSHIPS=true
```

---

## 12. Domain & SSL

| Domain | Purpose |
|---|---|
| `jkmichaels.com` | Marketing website (existing) |
| `app.jkmichaels.com` | Main application (PWA) |
| `api.jkmichaels.com` | API (if separated, initially same as app) |
| `staging.jkmichaels.com` | Staging environment |
| `cdn.jkmichaels.com` | CDN for static assets (Cloudflare) |

SSL: Managed by Cloudflare (free) or Let's Encrypt via Forge.
