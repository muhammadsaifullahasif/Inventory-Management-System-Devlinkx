#!/bin/bash

cd /home/u776021627/domains/inventory.satmec.com/public_html || exit 1

/usr/bin/php artisan schedule:run
