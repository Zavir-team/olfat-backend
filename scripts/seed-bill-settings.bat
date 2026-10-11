@echo off
cd /d "%~dp0.."
".tools\php\php.exe" artisan db:seed --class=SettingSeeder --force
exit /b %ERRORLEVEL%
