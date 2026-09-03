@echo off
echo ==========================================
echo EastBus Admin + Operator Portal Setup
echo ==========================================
where composer >nul 2>nul
if errorlevel 1 (
  echo Composer is not installed or not in PATH.
  echo Install Composer first: https://getcomposer.org
  pause
  exit /b 1
)
composer install
if not exist .env copy .env.example .env
php artisan key:generate
echo.
echo Create MySQL database: eastbus
echo Check DB settings in .env, then press any key.
pause
php artisan migrate --seed
php artisan serve
