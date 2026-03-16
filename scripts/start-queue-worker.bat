@echo off
REM ==============================================================================
REM Laravel Queue Worker for Windows
REM ==============================================================================
REM
REM This script runs the Laravel queue worker continuously.
REM
REM SETUP AS WINDOWS SERVICE (using NSSM):
REM   1. Download NSSM from https://nssm.cc/
REM   2. Run: nssm install InventoryQueueWorker
REM   3. Set Path to this batch file
REM   4. Set Startup directory to project root
REM   5. Click "Install service"
REM   6. Run: nssm start InventoryQueueWorker
REM
REM Or use Task Scheduler:
REM   1. Open Task Scheduler
REM   2. Create Basic Task
REM   3. Trigger: "When the computer starts"
REM   4. Action: Start this batch file
REM   5. Check "Run with highest privileges"
REM
REM ==============================================================================

cd /d "d:\Laravel\inventoryManagementSystem"

echo Starting Laravel Queue Worker...
echo Log file: storage\logs\worker.log
echo Press Ctrl+C to stop

:loop
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=512 >> storage\logs\worker.log 2>&1
echo Worker restarting... (waiting 5 seconds)
timeout /t 5 /nobreak > nul
goto loop
