#!/bin/bash

# Navigate to the project root or stop if it fails
cd /home/u776021627/domains/inventory.satmec.com/public_html || exit 1

# Run the queue worker cleanly
/usr/local/bin/php artisan queue:work database --queue=ebay-imports,inventory-sync,default --stop-when-empty --max-time=50 --timeout=1800
