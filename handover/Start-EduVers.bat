@echo off
rem ======================================================================
rem  EduVers - Start (the "Start EduVers" Desktop shortcut runs this)
rem
rem  Starts EduVers at http://127.0.0.1:8000 and opens it in the browser.
rem  Keep this window open while using EduVers. Close it to stop EduVers.
rem  When the database is on this computer, MySQL must be running:
rem  open Laragon and click "Start All".
rem ======================================================================
setlocal EnableExtensions
title EduVers - keep this window open while you use EduVers

for %%I in ("%~dp0..") do set "APP=%%~fI"
for %%I in ("%APP%\..\..") do set "LARAGON=%%~fI"
if not exist "%LARAGON%\bin\php" set "LARAGON=C:\laragon"
set "PORT=8000"

if not exist "%APP%\artisan" goto err_noapp
if not exist "%APP%\.env" goto err_noenv

set "PHP="
for /d %%D in ("%LARAGON%\bin\php\*") do call :checkphp "%%~fD\php.exe"
if not defined PHP goto err_nophp

rem Only wait for Laragon's MySQL when the database is on this computer
findstr /b /c:"DB_HOST=127.0.0.1" /c:"DB_HOST=localhost" "%APP%\.env" >nul 2>nul
if errorlevel 1 goto start_server
set "DBPORT=3306"
for /f "tokens=2 delims==" %%P in ('findstr /b /c:"DB_PORT=" "%APP%\.env"') do set "DBPORT=%%P"
set "MYSQLBIN="
for /d %%D in ("%LARAGON%\bin\mysql\*") do if exist "%%~fD\bin\mysqladmin.exe" set "MYSQLBIN=%%~fD\bin"
if not defined MYSQLBIN goto start_server

set "TRIES=0"
:mysql_wait
"%MYSQLBIN%\mysqladmin.exe" --host=127.0.0.1 --port=%DBPORT% --protocol=TCP --connect-timeout=3 --user=root ping >nul 2>nul
if not errorlevel 1 goto start_server
set /a TRIES+=1
if %TRIES% GTR 10 goto err_mysqldown
if %TRIES% EQU 1 if exist "%LARAGON%\laragon.exe" start "" "%LARAGON%\laragon.exe"
echo.
echo  MySQL is not running yet.
echo  In the Laragon window, click "Start All". When MySQL shows as started,
echo  come back to this window and press any key.
pause >nul
goto mysql_wait

:start_server
cd /d "%APP%"

rem When emails are switched on (EduVers Settings), a second small window sends them
rem in the background. Stop one left over from last time, then start a fresh one.
taskkill /FI "WINDOWTITLE eq EduVers email sender*" /F >nul 2>nul
rem Presentation mode uses the saved styles (public\build). Stop a left-over
rem "Edit EduVers" session and remove its "hot" file, or pages would look unstyled.
taskkill /FI "WINDOWTITLE eq EduVers live styles*" /T /F >nul 2>nul
taskkill /FI "WINDOWTITLE eq EduVers server*" /T /F >nul 2>nul
if exist "%APP%\public\hot" del /q "%APP%\public\hot"
findstr /b /c:"QUEUE_CONNECTION=database" "%APP%\.env" >nul 2>nul
if not errorlevel 1 start "EduVers email sender" /min "%PHP%" artisan queue:work --sleep=3 --tries=3 --max-time=43200

echo.
echo  EduVers is starting...
echo.
echo     Address : http://127.0.0.1:%PORT%
echo.
echo  Your browser opens in a few seconds.
echo  Keep this window open. To stop EduVers, close this window.
echo.
start "" /min cmd /c "timeout /t 4 /nobreak >nul & start http://127.0.0.1:%PORT%"
"%PHP%" artisan serve --host=127.0.0.1 --port=%PORT%
taskkill /FI "WINDOWTITLE eq EduVers email sender*" /F >nul 2>nul
echo.
echo  EduVers has stopped.
pause
exit /b 0

:checkphp
if defined PHP exit /b 0
if not exist "%~1" exit /b 0
"%~1" -r "exit(PHP_VERSION_ID >= 80300 ? 0 : 1);" >nul 2>nul
if errorlevel 1 exit /b 0
set "PHP=%~1"
exit /b 0

:err_noapp
echo  ERROR: EduVers was not found in %APP%
goto fail
:err_noenv
echo  ERROR: EduVers is not set up yet (there is no .env file).
echo         Run Install-EduVers.bat from the handover package first.
goto fail
:err_nophp
echo  ERROR: PHP 8.3 or newer was not found in %LARAGON%\bin\php
echo         Run Install-EduVers.bat from the handover package again.
goto fail
:err_mysqldown
echo  ERROR: MySQL is still not running. Open Laragon, click "Start All", then try again.
goto fail
:fail
echo.
pause
exit /b 1
