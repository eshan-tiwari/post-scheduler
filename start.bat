@echo off
echo ============================================
echo   Starting PostScheduler Full Stack SaaS
echo ============================================
echo.

echo [1/4] Starting Laravel API on http://localhost:8000 ...
cd /d "%~dp0laravel-backend"
start "Laravel API" cmd /k "php -S 127.0.0.1:8000 -t public"
timeout /t 2 /nobreak >nul

echo [2/4] Starting Laravel Queue Worker ...
start "Laravel Queue" cmd /k "php artisan queue:work"
timeout /t 1 /nobreak >nul

echo [3/4] Starting Laravel Scheduler ...
start "Laravel Scheduler" cmd /k "php artisan schedule:work"
timeout /t 1 /nobreak >nul

echo [4/4] Starting Angular Dev Server on http://localhost:4200 ...
cd /d "%~dp0auth-app"
start "Angular Frontend" cmd /k "npx ng serve"

echo.
echo ============================================
echo   All servers and workers are starting up!
echo   Backend:    http://localhost:8000
echo   Frontend:   http://localhost:4200
echo   Queue:      Running (artisan queue:work)
echo   Scheduler:  Running (artisan schedule:work)
echo ============================================
echo.
pause
