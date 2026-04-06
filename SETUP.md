# 🛠️ Setup & Deployment Guide

Complete step-by-step guide for setting up and deploying the Subscription Lifecycle Engine.

---

## 📋 Table of Contents

1. [Local Development Setup](#local-development-setup)
2. [Database Configuration](#database-configuration)
3. [API Authentication](#api-authentication)
4. [Scheduler Configuration](#scheduler-configuration)
5. [Production Deployment](#production-deployment)
6. [Monitoring & Maintenance](#monitoring--maintenance)
7. [Troubleshooting](#troubleshooting)

---

## 📱 Local Development Setup

### Step 1: Prerequisites

Ensure you have installed:
- PHP 8.3+ (`php --version`)
- Composer (`composer --version`)
- MySQL 8.0+ (`mysql --version`)
- Git (`git --version`)

### Step 2: Clone Repository

```bash
git clone <repository-url>
cd "Subscription Lifecycle Engine"
```

### Step 3: Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node dependencies (optional, for frontend assets)
npm install
npm run build
```

### Step 4: Setup Environment

```bash
# Copy environment template
cp .env.example .env

# Generate application key
php artisan key:generate
```

**Edit `.env` with your local settings:**

```env
APP_NAME="Subscription Lifecycle Engine"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC

LOG_CHANNEL=single

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_lifecycle
DB_USERNAME=root
DB_PASSWORD=

# API
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxx (auto-generated)
```

### Step 5: Create Database

```bash
# If using Laragon on Windows
# Database is auto-created through migration

# Or manually create
mysql -u root -p
CREATE DATABASE subscription_lifecycle;
EXIT;
```

### Step 6: Run Migrations

```bash
php artisan migrate
```

**Expected output:**
```
Migration table created successfully.
Migrated: 2026_04_05_000001_create_plans_table
Migrated: 2026_04_05_000002_create_plan_billing_cycles_table
Migrated: 2026_04_05_000003_create_plan_prices_table
Migrated: 2026_04_05_000004_create_subscriptions_table
Migrated: 2026_04_05_000005_create_subscription_histories_table
Migrated: 2026_04_05_000006_create_failed_payments_table
```

### Step 7: Start Development Server

```bash
# Terminal 1: Start Laravel server
php artisan serve
# Runs on http://localhost:8000

# Terminal 2: Run scheduler (for testing commands)
php artisan schedule:work
# Outputs scheduled task execution in real-time
```

### Step 8: Verify Installation

```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
=> PDOConnection instance
>>> exit

# Check scheduled commands
php artisan schedule:list
# Output should show 2 scheduled commands
```

---

## 💾 Database Configuration

### Connection Settings

The connection uses Laravel's default MySQL configuration from `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_lifecycle
DB_USERNAME=root
DB_PASSWORD=
```

### Database Tables

After migration, verify all tables exist:

```bash
mysql -u root subscription_lifecycle -e "SHOW TABLES;"
```

**Expected tables:**
```
+------------------------------------+
| Tables_for_subscription_lifecycle  |
+------------------------------------+
| failed_payments                    |
| migrations                         |
| plan_billing_cycles                |
| plan_prices                        |
| plans                              |
| subscription_histories             |
| subscriptions                      |
| users                              |
+------------------------------------+
```

### Timezone Configuration

**CRITICAL:** All timestamps must be in UTC!

**In `.env`:**
```env
APP_TIMEZONE=UTC
```

**In `config/app.php`:**
```php
'timezone' => env('APP_TIMEZONE', 'UTC'),
```

Verify with:
```bash
php artisan tinker
>>> Carbon\Carbon::now()
=> Carbon\Carbon @1712329200 {#3
     +"timezone": {..."UTC"...}
   }
```

---

## 🔐 API Authentication

### Setup Sanctum

Laravel Sanctum is already configured for API token authentication.

### Generate Personal Access Token

```bash
php artisan tinker

# Create user if needed
>>> $user = User::create([
  'name' => 'Test User',
  'email' => 'test@example.com',
  'password' => Hash::make('password'),
])

# Generate token
>>> $token = $user->createToken('api-token')->plainTextToken
=> "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"

# Exit tinker
>>> exit
```

### Use Token in Requests

Include token in Authorization header:

```bash
curl -H "Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  http://localhost:8000/api/v1/subscriptions
```

Or in Postman:
1. Add header: `Authorization: Bearer {token}`
2. All authenticated endpoints now accessible

---

## ⏰ Scheduler Configuration

### Local Testing

```bash
# Terminal 1: Start Laravel
php artisan serve

# Terminal 2: Watch scheduler execution
php artisan schedule:work
```

**Output example:**
```
Scheduler running. Use CTRL+C to exit.

[2026-04-05 00:01] Running scheduled command: subscriptions:expire-trials
[2026-04-05 00:01] Command finished in 125ms
[2026-04-05 01:00] Running scheduled command: subscriptions:process-grace-period
[2026-04-05 01:00] Command finished 234ms
```

### Test Commands with Dry-Run

```bash
# Test trial expiry (no changes)
php artisan subscriptions:expire-trials --dry-run
# Output:
# 🔍 DRY RUN MODE - No changes will be made
# 🔄 Processing expired trials at 2026-04-05T15:30:00.000000Z
# ✅ No expired trials found

# Test grace period (no changes)
php artisan subscriptions:process-grace-period --dry-run
# Output:
# 🔍 DRY RUN MODE - No changes will be made
# 🔄 Processing expired grace periods at 2026-04-05T15:32:00.000000Z
# ✅ No expired grace periods found
```

### Production Configuration

Add to system crontab:

```bash
# Edit crontab
crontab -e

# Add this line:
* * * * * cd /var/www/subscription-engine && php artisan schedule:run >> /dev/null 2>&1
```

This runs every minute and executes any scheduled commands whose time has come.

---

## 🚀 Production Deployment

### Step 1: Prepare Server

```bash
# SSH into server
ssh user@your-domain.com

# Clone repository
cd /var/www
git clone <repository-url> subscription-engine
cd subscription-engine
```

### Step 2: Install Dependencies

```bash
composer install --optimize-autoloader --no-dev
npm install --production
npm run build
```

### Step 3: Environment Setup

```bash
# Copy and configure .env
cp .env.example .env

# Edit .env for production
nano .env
```

**Production .env settings:**

```env
APP_ENV=production
APP_DEBUG=false

APP_URL=https://your-domain.com
APP_TIMEZONE=UTC

# Database
DB_HOST=localhost
DB_DATABASE=subscription_prod
DB_USERNAME=sub_user
DB_PASSWORD=strong_password_here

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Step 4: Run Migrations

```bash
php artisan migrate --force
```

### Step 5: Configure Web Server

#### Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    root /var/www/subscription-engine/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

### Step 6: Install Systemd Services

**Create `/etc/systemd/system/subscription-scheduler.service`:**

```ini
[Unit]
Description=Subscription Lifecycle Scheduler
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/subscription-engine
ExecStart=/usr/bin/php artisan schedule:work
Restart=always
RestartSec=10
StandardOutput=append:/var/log/subscription-scheduler.log
StandardError=append:/var/log/subscription-scheduler.log

[Install]
WantedBy=multi-user.target
```

**Create `/etc/systemd/system/subscription-queue.service`:**

```ini
[Unit]
Description=Subscription Lifecycle Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/subscription-engine
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3
Restart=always
RestartSec=10
StandardOutput=append:/var/log/subscription-queue.log
StandardError=append:/var/log/subscription-queue.log

[Install]
WantedBy=multi-user.target
```

**Enable services:**

```bash
sudo systemctl daemon-reload
sudo systemctl enable subscription-scheduler.service
sudo systemctl enable subscription-queue.service
sudo systemctl start subscription-scheduler.service
sudo systemctl start subscription-queue.service

# Verify running
sudo systemctl status subscription-scheduler.service
sudo systemctl status subscription-queue.service
```

### Step 7: Setup SSL Certificate

Using Let's Encrypt with Certbot:

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot certonly --nginx -d your-domain.com
```

### Step 8: Configure Firewall

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

---

## 📊 Monitoring & Maintenance

### Check Application Health

```bash
# View recent logs
tail -f /var/www/subscription-engine/storage/logs/laravel.log

# Check service status
sudo systemctl status subscription-scheduler.service
sudo systemctl status subscription-queue.service

# View scheduler executions
tail -f /var/log/subscription-scheduler.log
tail -f /var/log/subscription-queue.log
```

### Database Maintenance

```bash
# Backup database
mysqldump -u sub_user -p subscription_prod > backup_$(date +%Y%m%d).sql

# Optimize tables
php artisan optimize
php artisan config:cache
php artisan route:cache
```

### Performance Monitoring

```bash
# Check database query count
php artisan tinker
>>> \DB::enableQueryLog();
>>> \App\Models\Subscription::all();
>>> \DB::getQueryLog();
```

### Logs Rotation

Configure log rotation in `/etc/logrotate.d/subscription-engine`:

```log
/var/www/subscription-engine/storage/logs/laravel.log
/var/log/subscription-*.log
{
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
}
```

---

## 🔧 Troubleshooting

### Issue: Command not found

```bash
# Ensure commands are registered
php artisan list

# Should show:
# subscriptions:expire-trials
# subscriptions:process-grace-period
```

### Issue: Database connection error

```bash
# Test connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check .env settings
grep DB_ .env

# Verify MySQL running
sudo systemctl status mysql
```

### Issue: Scheduler not running

```bash
# Check if cron job is added
crontab -l

# Should show:
# * * * * * cd /var/www/subscription-engine && php artisan schedule:run >> /dev/null 2>&1

# Test manually
php artisan schedule:run

# Or use Systemd service
sudo systemctl status subscription-scheduler.service
```

### Issue: Timezone problems

```bash
# Verify APP_TIMEZONE in .env
cat .env | grep APP_TIMEZONE

# Check system timezone
date

# Set system timezone (if needed)
sudo timedatectl set-timezone UTC
```

### Issue: Permission errors

```bash
# Fix storage directory permissions
sudo chown -R www-data:www-data /var/www/subscription-engine/storage
sudo chmod -R 755 /var/www/subscription-engine/storage
sudo chmod -R 755 /var/www/subscription-engine/bootstrap/cache
```

### Issue: Out of memory

```bash
# Increase PHP memory limit in php.ini
memory_limit = 512M

# Or with artisan
php -d memory_limit=512M artisan migrate
```

---

## ✅ Deployment Checklist

- [ ] Clone repository
- [ ] Run composer install
- [ ] Configure .env for environment
- [ ] Generate APP_KEY
- [ ] Create database
- [ ] Run migrations
- [ ] Setup API authentication (Sanctum tokens)
- [ ] Configure web server (Nginx/Apache)
- [ ] Setup SSL certificate
- [ ] Install cron job or systemd service
- [ ] Test scheduler commands (dry-run)
- [ ] Setup logging and monitoring
- [ ] Test API endpoints
- [ ] Configure backups
- [ ] Document deployment

---

## 📞 Support

For issues or questions, refer to:
- [README.md](README.md) - Quick start guide
- [PLAN.md](PLAN.md) - Implementation details
- [ARCHITECTURE.md](ARCHITECTURE.md) - System design

---

**Last Updated:** April 5, 2026
