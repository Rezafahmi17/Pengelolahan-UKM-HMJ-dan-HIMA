@echo off
cd /d %~dp0
if not exist .env copy .env.example .env >nul
echo.
echo SIM ORMAWA ^& HMJ berjalan di http://127.0.0.1:8000
echo Tekan Ctrl+C untuk berhenti.
echo.
php -S 127.0.0.1:8000 -t public
pause
