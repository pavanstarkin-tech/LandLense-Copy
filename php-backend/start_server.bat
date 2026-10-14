@echo off
echo ====================================================
echo   🚀 Starting LandLens Enterprise PHP API Server
echo   Port: 5000 (http://localhost:5000)
echo ====================================================
cd /d "%~dp0"
php -S 0.0.0.0:5000 router.php
pause
