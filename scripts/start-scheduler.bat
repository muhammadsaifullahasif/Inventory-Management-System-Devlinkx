@echo off
REM ==============================================================================
REM Laravel Scheduler for Windows
REM ==============================================================================
REM
REM This script runs the Laravel scheduler every minute.
REM
REM SETUP AS WINDOWS SERVICE (using NSSM):
REM   1. Download NSSM from https://nssm.cc/
REM   2. Run: nssm install InventoryScheduler
REM   3. Set Path to this batch file
REM   4. Set Startup directory to project root
REM   5. Click "Install service"
REM   6. Run: nssm start InventoryScheduler
REM
REM Or use Task Scheduler (RECOMMENDED):
REM   1. Open Task Scheduler
REM   2. Create Task (not Basic Task)
REM   3. Trigger: Daily, repeat every 1 minute for 1 day
REM   4. Action: php artisan schedule:run
REM   5. Start in: d:\Laravel\inventoryManagementSystem
REM
REM ==============================================================================

cd /d "d:\Laravel\inventoryManagementSystem"

echo Starting Laravel Scheduler...
echo Log file: storage\logs\scheduler.log
echo Press Ctrl+C to stop

:loop
echo [%date% %time%] Running scheduler... >> storage\logs\scheduler.log
php artisan schedule:run --verbose --no-interaction >> storage\logs\scheduler.log 2>&1
echo Waiting 60 seconds...
timeout /t 60 /nobreak > nul
goto loop
