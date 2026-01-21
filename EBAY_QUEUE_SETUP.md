# eBay Job Queue Setup Guide

## Overview
I've created a complete job queue system for importing eBay listings. This allows you to import listings asynchronously without blocking the UI.

## Files Created

### 1. **Job Class** - `app/Jobs/ImportEbayListings.php`
- Handles the import logic for eBay listings
- Implements `ShouldQueue` and `ShouldBeUnique` interfaces
- Supports both 'active' and 'unsold' listings
- Includes automatic token refresh
- Processes items in chunks for memory efficiency
- Retry logic (3 attempts by default)
- Timeout: 1 hour per job

### 2. **Queue Controller** - `app/Http/Controllers/EbayImportQueueController.php`
- `dispatchActiveListingsJob()` - Queue job for active listings
- `dispatchUnsoldListingsJob()` - Queue job for unsold listings
- `dispatchWithDelay()` - Queue job with custom delay

### 3. **Updated Routes** - `routes/web.php`
```
POST /ebay/import/active/{id}        - Start active listings import
POST /ebay/import/unsold/{id}         - Start unsold listings import
POST /ebay/import/delayed/{id}        - Schedule import with delay
GET /ebay/queue-status               - View queue status
```

---

## Setup Instructions

### Step 1: Configure Queue Driver
Edit `.env` file:
```env
QUEUE_CONNECTION=database    # or 'redis', 'sync' for testing
```

### Step 2: Create Queue Table (if using database)
```bash
php artisan queue:table
php artisan migrate
```

### Step 3: Start Queue Worker
```bash
# For development (single worker)
php artisan queue:work

# For production (with options)
php artisan queue:work --queue=default --tries=3 --timeout=3600

# Using Supervisor (recommended for production)
# See: https://laravel.com/docs/queues#supervisor-configuration
```

### Step 4: Configure SalesChannel Model
Ensure your `SalesChannel` model has these fields:
- `access_token`
- `token_expires_at`
- `refresh_token`
- `client_id`
- `client_secret`
- `user_scopes`

### Step 5: Update Product Model
Add to `app/Models/Product.php`:
```php
// If not already exists
protected $fillable = [
    'sku',
    'name',
    'description',
    'price',
    'quantity',
    'warehouse_id',
    'rack_id',
    'sales_channel_id',
    'external_id',
    'source',
    // ... other fields
];
```

---

## Usage

### Method 1: Manual Dispatch (From Controller)
```php
// In your controller
use App\Jobs\ImportEbayListings;

// Dispatch immediately
ImportEbayListings::dispatch($salesChannelId, 'active');

// With delay
ImportEbayListings::dispatch($salesChannelId, 'active')
    ->delay(now()->addMinutes(5));

// Custom queue
ImportEbayListings::dispatch($salesChannelId, 'active')
    ->onQueue('imports');
```

### Method 2: HTTP Routes
```
# Queue active listings import
POST /ebay/import/active/{salesChannelId}

# Queue unsold listings import  
POST /ebay/import/unsold/{salesChannelId}

# Queue with delay
POST /ebay/import/delayed/{salesChannelId}
Body:
{
    "job_type": "active",        // or "unsold"
    "delay_minutes": 5
}
```

### Method 3: Scheduled Jobs
Add to `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    // Import every 6 hours
    $schedule->job(new ImportEbayListings($salesChannelId, 'active'))
        ->everyThreeHours()
        ->onQueue('default');
}
```

### Method 4: Artisan Command (Create your own)
```php
// app/Console/Commands/ImportEbayListings.php
$this->dispatch(new \App\Jobs\ImportEbayListings($id, 'active'));
```

---

## Job Features

### Unique Jobs
The job implements `ShouldBeUnique`, preventing duplicate imports:
```php
public function uniqueId(): string
{
    return "import-ebay-listings-{$this->salesChannelId}";
}
```

### Automatic Token Refresh
Tokens are automatically refreshed if expired:
```php
if ($this->isTokenExpired($salesChannel)) {
    $this->refreshToken($salesChannel, $ebayService);
}
```

### Error Handling
- Logs all errors and exceptions
- Automatically retries on failure (3 times)
- Chunks data processing for memory efficiency
- Transaction-based commits for data integrity

### Monitoring
View job status in queue:
```bash
# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {id}

# Flush all failed jobs
php artisan queue:flush
```

---

## Configuration Options

### In Job Class
```php
public $timeout = 3600;    // Job timeout in seconds
public $tries = 3;          // Number of retry attempts
public $retryAfter = 5;     // Delay before retry in minutes
```

### Chunk Size
Adjust in `handle()` method:
```php
$chunkSize = 100;  // Process 100 items per transaction
```

---

## Testing Queue Locally

### Sync Driver (For Development)
Edit `.env`:
```env
QUEUE_CONNECTION=sync
```
Jobs will execute immediately without queueing.

### Monitor Queue with Horizon (Premium)
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```
Visit: http://localhost:8000/horizon

---

## Database Schema (if using database queue)

The migration creates these tables:
```
jobs                    - Pending jobs
job_batches            - Job batches
failed_jobs            - Failed jobs
```

---

## Troubleshooting

### Jobs Not Processing
```bash
# Check if worker is running
php artisan queue:work

# Debug mode
php artisan queue:work --debug

# Check failed jobs
php artisan queue:failed
```

### Token Expiration Issues
Ensure `token_expires_at` is set in `sales_channels` table:
```sql
ALTER TABLE sales_channels ADD COLUMN token_expires_at TIMESTAMP NULL;
```

### Memory Issues
Reduce chunk size in job:
```php
$chunkSize = 50;  // Instead of 100
```

### High Server Load
Use multiple workers:
```bash
php artisan queue:work &
php artisan queue:work &
php artisan queue:work &
```

---

## Advanced Configuration

### Redis Queue (Recommended for Production)
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Multiple Queues
```php
// High priority
ImportEbayListings::dispatch($id, 'active')
    ->onQueue('high');

// Low priority
ImportEbayListings::dispatch($id, 'active')
    ->onQueue('low');

// Worker command
php artisan queue:work --queues=high,default,low
```

### Job Middleware
Add rate limiting:
```php
public function middleware(): array
{
    return [new RateLimited];
}
```

---

## Next Steps

1. ✅ Test the job locally with `QUEUE_CONNECTION=sync`
2. ✅ Add UI buttons to trigger imports
3. ✅ Set up queue worker monitoring
4. ✅ Configure Supervisor for production
5. ✅ Add job failure notifications
6. ✅ Create dashboard to monitor queue status
