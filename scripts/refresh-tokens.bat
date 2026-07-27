@echo off
REM ==============================================================================
REM Manual Token Refresh Script for Windows
REM ==============================================================================
REM
REM Use this script to manually refresh all API tokens.
REM Useful for testing or emergency token refresh.
REM
REM ==============================================================================

cd /d "d:\Laravel\inventoryManagementSystem"

echo.
echo ==============================================================================
echo Refreshing eBay Tokens...
echo ==============================================================================
php artisan tokens:refresh-ebay --force

echo.
echo ==============================================================================
echo Refreshing Shipping Carrier Tokens...
echo ==============================================================================
php artisan tokens:refresh-shipping --force

echo.
echo ==============================================================================
echo Token refresh complete!
echo ==============================================================================
pause
