#!/bin/bash

cd /home/u776021627/domains/inventory.satmec.com/public_html || exit 1

/usr/bin/php artisan queue:work database --queue=ebay-imports,inventory-sync,default --stop-when-empty --max-time=50 --timeout=1800
