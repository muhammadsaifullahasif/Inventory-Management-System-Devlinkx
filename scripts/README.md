# Background Jobs & Scheduler Setup

This document explains how to set up background processing for the Inventory Management System.

## Overview

The system requires two background processes:

1. **Queue Worker** - Processes background jobs (e.g., sending emails, syncing data)
2. **Scheduler** - Runs scheduled tasks (e.g., token refresh, delivery status checks)

## Scheduled Tasks

The following tasks run automatically:

| Task | Schedule | Description |
|------|----------|-------------|
| `tokens:refresh-ebay` | Every 30 minutes | Refreshes eBay API tokens before expiry |
| `tokens:refresh-shipping` | Every 30 minutes | Refreshes FedEx/shipping API tokens before expiry |
| `orders:check-delivery-status` | Every 2 hours | Checks tracking status for shipped orders |
| `UpdateEbayOrderStatusJob` | Twice daily (6 AM & 6 PM) | Syncs order statuses from eBay |

## Windows Setup

### Option 1: NSSM (Recommended for Production)

NSSM (Non-Sucking Service Manager) creates proper Windows services.

1. Download NSSM from https://nssm.cc/
2. Extract to a folder (e.g., `C:\nssm`)
3. Open Command Prompt as Administrator

**Install Queue Worker Service:**
```cmd
nssm install InventoryQueueWorker
```
- Path: `d:\Laravel\inventoryManagementSystem\scripts\start-queue-worker.bat`
- Startup directory: `d:\Laravel\inventoryManagementSystem`
- Click "Install service"

**Install Scheduler Service:**
```cmd
nssm install InventoryScheduler
```
- Path: `d:\Laravel\inventoryManagementSystem\scripts\start-scheduler.bat`
- Startup directory: `d:\Laravel\inventoryManagementSystem`
- Click "Install service"

**Start the services:**
```cmd
nssm start InventoryQueueWorker
nssm start InventoryScheduler
```

**Manage services:**
```cmd
nssm status InventoryQueueWorker
nssm stop InventoryQueueWorker
nssm restart InventoryQueueWorker
nssm remove InventoryQueueWorker confirm
```

### Option 2: Windows Task Scheduler

For the **Scheduler** (runs every minute):

1. Open Task Scheduler
2. Click "Create Task" (not Basic Task)
3. **General tab:**
   - Name: `Laravel Scheduler`
   - Check "Run whether user is logged on or not"
   - Check "Run with highest privileges"
4. **Triggers tab:**
   - New → Daily
   - Check "Repeat task every: 1 minute"
   - For a duration of: Indefinitely
5. **Actions tab:**
   - Action: Start a program
   - Program: `php`
   - Arguments: `artisan schedule:run`
   - Start in: `d:\Laravel\inventoryManagementSystem`
6. Click OK and enter your password

For the **Queue Worker**:
- Same steps, but trigger "At startup" instead of repeating
- Program: `d:\Laravel\inventoryManagementSystem\scripts\start-queue-worker.bat`

## Linux/Ubuntu Setup (Production Server)

### Server Details
- **Project Path:** `/home/u776021627/domains/inventory.satmec.com/public_html`
- **PHP Path:** `/usr/bin/php`

### Quick Setup (Copy & Paste)

SSH into your server and run these commands:

```bash
# 1. Navigate to project directory
cd /home/u776021627/domains/inventory.satmec.com/public_html

# 2. Check if supervisor is installed
which supervisord

# 3. If not installed, install it:
sudo apt-get update && sudo apt-get install supervisor -y

# 4. Copy the config file
sudo cp supervisor.conf /etc/supervisor/conf.d/inventory-system.conf

# 5. Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update

# 6. Start the processes
sudo supervisorctl start inventory-system:*

# 7. Check status
sudo supervisorctl status
```

### Expected Output

After running `sudo supervisorctl status`, you should see:
```
inventory-system:inventory-scheduler   RUNNING   pid 12345, uptime 0:00:05
inventory-system:inventory-worker-00   RUNNING   pid 12346, uptime 0:00:05
inventory-system:inventory-worker-01   RUNNING   pid 12347, uptime 0:00:05
```

### Useful Supervisor Commands

```bash
# Check status
sudo supervisorctl status

# Restart all processes
sudo supervisorctl restart inventory-system:*

# Stop all processes
sudo supervisorctl stop inventory-system:*

# Start all processes
sudo supervisorctl start inventory-system:*

# View worker logs (live)
sudo supervisorctl tail -f inventory-worker-00

# View scheduler logs (live)
sudo supervisorctl tail -f inventory-scheduler

# View last 100 lines of worker log
tail -100 /home/u776021627/domains/inventory.satmec.com/public_html/storage/logs/worker.log

# View last 100 lines of scheduler log
tail -100 /home/u776021627/domains/inventory.satmec.com/public_html/storage/logs/scheduler.log

# View token refresh log
tail -100 /home/u776021627/domains/inventory.satmec.com/public_html/storage/logs/token-refresh.log

# Reload configuration after changes
sudo supervisorctl reread
sudo supervisorctl update
```

### If Supervisor is Not Available (Shared Hosting Alternative)

If you can't install supervisor (shared hosting), use cron instead:

```bash
# Your existing cron job for scheduler (already configured):
* * * * * /usr/bin/php /home/u776021627/domains/inventory.satmec.com/public_html/artisan schedule:run >> /dev/null 2>&1

# Add this for queue worker (processes jobs every minute):
* * * * * /usr/bin/php /home/u776021627/domains/inventory.satmec.com/public_html/artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

Note: Cron-only setup processes queue jobs once per minute. Supervisor is preferred for continuous processing.

## Manual Token Refresh

To manually refresh all tokens:

**Windows:**
```cmd
scripts\refresh-tokens.bat
```

**Linux/Mac:**
```bash
php artisan tokens:refresh-ebay --force
php artisan tokens:refresh-shipping --force
```

## Checking Token Status

To check which tokens need refreshing:

```bash
php artisan tokens:refresh-ebay
php artisan tokens:refresh-shipping
```

Without `--force`, it only refreshes tokens expiring within 30 minutes.

## Log Files

- Queue Worker: `storage/logs/worker.log`
- Scheduler: `storage/logs/scheduler.log`
- Token Refresh: `storage/logs/token-refresh.log`
- Delivery Status: `storage/logs/delivery-status.log`
- eBay Order Status: `storage/logs/ebay-order-status.log`

## Troubleshooting

### Tokens not refreshing?

1. Check if scheduler is running:
```bash
php artisan schedule:list
```

2. Run token refresh manually:
```bash
php artisan tokens:refresh-ebay -v
php artisan tokens:refresh-shipping -v
```

3. Check the logs:
```bash
tail -f storage/logs/token-refresh.log
```

### Queue jobs not processing?

1. Check if queue worker is running
2. View failed jobs:
```bash
php artisan queue:failed
```

3. Retry failed jobs:
```bash
php artisan queue:retry all
```

4. Clear stuck jobs:
```bash
php artisan queue:flush
```
