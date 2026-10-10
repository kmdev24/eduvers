@echo off
rem ======================================================================
rem  EduVers - Edit mode (the "Edit EduVers" Desktop shortcut runs this)
rem
rem  Use this when you want to CHANGE the code. It:
rem    1. opens the project in Visual Studio Code (if it is installed)
rem    2. starts "live styles" (npm run dev): saved changes to pages, CSS
rem       and JavaScript show in the browser by themselves
rem    3. starts EduVers at http://127.0.0.1:8000
rem
rem  When you finish, come back to THIS window and press any key. It stops
rem  everything and saves your style changes (npm run build), so that
rem  "Start EduVers" (presentation mode) shows the newest version.
rem ======================================================================
setlocal EnableExtensions
title EduVers - Edit mode (press a key in this window when you finish)

for %%I in ("%~dp0..") do set "APP=%%~fI"
for %%I in ("%APP%\..\..") do set "LARAGON=%%~fI"
if not exist "%LARAGON%\bin\php" set "LARAGON=C:\laragon"
set "PORT=8000"

if not exist "%APP%\artisan" goto err_noapp
if not exist "%APP%\.env" goto err_noenv

set "PHP="
for /d %%D in ("%LARAGON%\bin\php\*") do call :checkphp "%%~fD\php.exe"
if not defined PHP goto err_nophp

rem Live styles need Node.js 20.19+ or 22.12+ (the installer from nodejs.org, or Laragon's)
set "NODEDIR="
for /f "delims=" %%P in ('where node 2^>nul') do call :checknode "%%~dpP."
for /d %%D in ("%LARAGON%\bin\nodejs\*") do call :checknode "%%~fD"
if defined NODEDIR set "PATH=%NODEDIR%;%PATH%"

cd /d "%APP%"
echo.
echo  EduVers - Edit mode
echo  ===================
echo.

rem Stop anything left over from last time (edit mode or presentation mode)
call :stopall

rem ---------------------------------------------------------------- 1
echo [1/4] Opening the code editor...
set "CODE="
for /f "delims=" %%P in ('where code.cmd 2^>nul') do if not defined CODE set "CODE=%%P"
if not defined CODE if exist "%LOCALAPPDATA%\Programs\Microsoft VS Code\bin\code.cmd" set "CODE=%LOCALAPPDATA%\Programs\Microsoft VS Code\bin\code.cmd"
if not defined CODE if exist "%ProgramFiles%\Microsoft VS Code\bin\code.cmd" set "CODE=%ProgramFiles%\Microsoft VS Code\bin\code.cmd"
if not defined CODE goto code_missing
call "%CODE%" "%APP%"
echo       Visual Studio Code opened the project.
goto code_done
:code_missing
echo       Visual Studio Code was not found, so the project folder opens instead.
echo       Get it free from https://code.visualstudio.com/ (any editor works).
start "" explorer "%APP%"
:code_done

rem ---------------------------------------------------------------- 2
echo.
echo [2/4] Starting live styles...
set "LIVE=0"
if not defined NODEDIR goto live_nonode
if exist "%APP%\node_modules\.bin\vite.cmd" goto live_start
echo       Installing the style tools for the first time (needs internet, about a minute)...
call npm install --no-audit --no-fund
if errorlevel 1 goto live_failed
:live_start
if exist "%APP%\public\hot" del /q "%APP%\public\hot"
start "EduVers live styles" /min cmd /c "npm run dev"
set "TRIES=0"
:live_wait
if exist "%APP%\public\hot" goto live_ok
set /a TRIES+=1
if %TRIES% GTR 40 goto live_failed
timeout /t 1 /nobreak >nul
goto live_wait
:live_ok
set "LIVE=1"
echo       Live styles are on.
goto live_done
:live_failed
taskkill /FI "WINDOWTITLE eq EduVers live styles*" /T /F >nul 2>nul
if exist "%APP%\public\hot" del /q "%APP%\public\hot"
echo       Live styles could not start (see the messages above).
echo       EduVers still runs with the last saved styles.
goto live_done
:live_nonode
echo       Node.js 20.19 or newer was not found, so live styles are off.
echo       PHP and page edits still show after you refresh the browser (F5).
echo       New Tailwind classes or CSS changes need Node.js: install the LTS
echo       version from https://nodejs.org/ and open Edit EduVers again.
:live_done

rem ---------------------------------------------------------------- 3
echo.
echo [3/4] Starting EduVers...
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
echo       MySQL is not running yet.
echo       In the Laragon window, click "Start All". When MySQL shows as started,
echo       come back to this window and press any key.
pause >nul
goto mysql_wait

:start_server
findstr /b /c:"QUEUE_CONNECTION=database" "%APP%\.env" >nul 2>nul
if not errorlevel 1 start "EduVers email sender" /min "%PHP%" artisan queue:work --sleep=3 --tries=3 --max-time=43200
start "EduVers server" /min "%PHP%" artisan serve --host=127.0.0.1 --port=%PORT%
timeout /t 4 /nobreak >nul
start "" http://127.0.0.1:%PORT%

rem ---------------------------------------------------------------- 4
echo.
echo [4/4] Ready - edit and save your files (Ctrl+S).
echo.
echo     Address : http://127.0.0.1:%PORT%
echo.
if "%LIVE%"=="1" echo     Pages in resources\views, CSS and JavaScript : the browser updates by itself
if "%LIVE%"=="0" echo     Pages in resources\views                     : refresh the browser with F5
echo     PHP code in app\, routes\, config\          : refresh the browser with F5
echo     .env settings                               : finish here, then open Edit EduVers again
echo     New tables in database\migrations          : run "php artisan migrate" in Laragon's Terminal
echo.
echo  If a page shows an error, the details are in storage\logs\laravel.log
echo.
echo  ------------------------------------------------------------------
echo   When you FINISH editing, come back here and press any key.
echo   (Do not just close this window - your style changes would not be saved.)
echo  ------------------------------------------------------------------
pause >nul

echo.
echo  Stopping...
call :stopall
if exist "%APP%\public\hot" del /q "%APP%\public\hot"
"%PHP%" artisan optimize:clear >nul 2>nul
if not defined NODEDIR goto build_skip
if not exist "%APP%\node_modules\.bin\vite.cmd" goto build_skip
echo  Saving the styles for presentation mode (npm run build)...
call npm run build
if errorlevel 1 goto build_failed
echo.
echo  Done. Your changes are saved.
echo  Use "Start EduVers" on the Desktop to present.
goto finish
:build_failed
echo.
echo  WARNING: saving the styles failed (see the messages above).
echo  The last working styles are still used. Fix the error, then open
echo  Edit EduVers again and finish normally.
goto finish
:build_skip
echo  Done. (Styles were not rebuilt because Node.js is not available.)
:finish
echo.
pause
exit /b 0

rem ======================================================================
:stopall
taskkill /FI "WINDOWTITLE eq EduVers live styles*" /T /F >nul 2>nul
taskkill /FI "WINDOWTITLE eq EduVers server*" /T /F >nul 2>nul
taskkill /FI "WINDOWTITLE eq EduVers email sender*" /T /F >nul 2>nul
exit /b 0

:checkphp
if defined PHP exit /b 0
if not exist "%~1" exit /b 0
"%~1" -r "exit(PHP_VERSION_ID >= 80300 ? 0 : 1);" >nul 2>nul
if errorlevel 1 exit /b 0
set "PHP=%~1"
exit /b 0

:checknode
rem Argument: a folder with node.exe. Sets NODEDIR when Node is new enough for Vite.
if defined NODEDIR exit /b 0
if not exist "%~1\node.exe" exit /b 0
if not exist "%~1\npm.cmd" exit /b 0
"%~1\node.exe" -e "var v=process.versions.node.split('.').map(Number);process.exit(v[0]>=23||v[0]==22&&v[1]>=12||v[0]==20&&v[1]>=19?0:1)" >nul 2>nul
if errorlevel 1 exit /b 0
set "NODEDIR=%~1"
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
call :stopall
if exist "%APP%\public\hot" del /q "%APP%\public\hot"
echo  ERROR: MySQL is still not running. Open Laragon, click "Start All", then try again.
goto fail
:fail
echo.
pause
exit /b 1
