<?php

/**
 * Quick script to add multiple eBay User IDs to GLORIX sales channel
 * Run: php update_glorix_user_ids.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SalesChannel;

// Find GLORIX
$glorix = SalesChannel::where('name', 'LIKE', '%GLORIX%')->first();

if (!$glorix) {
    echo "GLORIX sales channel not found!\n";
    exit(1);
}

echo "Found GLORIX: ID {$glorix->id}, Name: {$glorix->name}\n";
echo "Current ebay_user_id: {$glorix->ebay_user_id}\n";
echo "Current ebay_user_ids: " . json_encode($glorix->ebay_user_ids) . "\n\n";

// Add all known user IDs for GLORIX (from notification logs)
$knownUserIds = [
    'asjiv-24',      // Primary user ID (from GetUser API)
    'hbpvx8phsva',   // Return notifications
    'mx7c0lh6qxa',   // Return notifications
];

$glorix->ebay_user_ids = $knownUserIds;
$glorix->save();

echo "Updated ebay_user_ids to: " . json_encode($glorix->ebay_user_ids) . "\n";
echo "Done!\n";
