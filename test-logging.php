<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test logging to both channels
\Illuminate\Support\Facades\Log::info('TEST: Default log channel working');
\Illuminate\Support\Facades\Log::channel('ebay')->info('TEST: eBay log channel working');

echo "Test logs written. Check:\n";
echo "- storage/logs/laravel.log for default log\n";
echo "- storage/logs/ebay/ebay-" . date('Y-m-d') . ".log for ebay log\n";
