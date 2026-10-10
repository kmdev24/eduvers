@echo off
rem ======================================================================
rem  EduVers - Settings form (the "EduVers Settings" Desktop shortcut)
rem
rem  Opens a form in the web browser where the database, email and address
rem  settings are typed in. No file editing is needed.
rem
rem  The installer runs this as:  Setup-EduVers.bat install
rem  Exit code: 0 = settings were saved, 1 = not saved.
rem ======================================================================
setlocal EnableExtensions
title EduVers - Settings

for %%I in ("%~dp0..") do set "APP=%%~fI"
for %%I in ("%APP%\..\..") do set "LARAGON=%%~fI"
if not exist "%LARAGON%\bin\php" set "LARAGON=C:\laragon"
set "MODE=settings"
if /i "%~1"=="install" set "MODE=install"
set "SETUPPORT=8765"

if not exist "%APP%\artisan" goto err_noapp
if not exist "%APP%\.env" goto err_noenv
if not exist "%APP%\handover\setup\index.php" goto err_noform

set "PHP="
for /d %%D in ("%LARAGON%\bin\php\*") do call :checkphp "%%~fD\php.exe"
if not defined PHP goto err_nophp

rem Stop a settings form left open by an earlier run
taskkill /FI "WINDOWTITLE eq EduVers settings form*" /F >nul 2>nul

set "EDUVERS_SETUP_TOKEN=%RANDOM%%RANDOM%%RANDOM%%RANDOM%%RANDOM%"
set "EDUVERS_SETUP_MODE=%MODE%"
set "EDUVERS_SETUP_DONE=%TEMP%\eduvers-setup-done-%RANDOM%.txt"
if exist "%EDUVERS_SETUP_DONE%" del /q "%EDUVERS_SETUP_DONE%"

start "EduVers settings form" /min "%PHP%" -S 127.0.0.1:%SETUPPORT% "%APP%\handover\setup\index.php"
timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:%SETUPPORT%/?token=%EDUVERS_SETUP_TOKEN%"

echo.
echo  The EduVers settings form is now open in your web browser.
echo.
echo     1. Fill in the form.
echo     2. Click "Save settings".
echo     3. Come back here - this window continues by itself.
echo.
echo  Did the browser not open? Type this address in it:
echo     http://127.0.0.1:%SETUPPORT%/?token=%EDUVERS_SETUP_TOKEN%
echo.
echo  Waiting for you to save the form...  (to cancel, close this window)

:wait_saved
if exist "%EDUVERS_SETUP_DONE%" goto saved
timeout /t 1 /nobreak >nul
goto wait_saved

:saved
del /q "%EDUVERS_SETUP_DONE%" >nul 2>nul
timeout /t 2 /nobreak >nul
taskkill /FI "WINDOWTITLE eq EduVers settings form*" /F >nul 2>nul
echo.
echo  Settings saved.
if "%MODE%"=="install" exit /b 0

rem Settings changed on an installed copy: make sure the database has its tables
echo.
echo  Checking the database with the new settings...
pushd "%APP%"
"%PHP%" artisan optimize:clear >nul 2>nul
"%PHP%" artisan migrate --force
if errorlevel 1 goto err_migrate
popd
echo.
echo  Finished. Close EduVers (its black window) and start it again
echo  so that it uses the new settings.
echo.
pause
exit /b 0

:checkphp
if defined PHP exit /b 0
if not exist "%~1" exit /b 0
"%~1" -r "exit(PHP_VERSION_ID >= 80300 ? 0 : 1);" >nul 2>nul
if errorlevel 1 exit /b 0
set "PHP=%~1"
exit /b 0

:err_migrate
popd
echo.
echo  The settings were saved, but EduVers could not use the database.
echo  The message above says why. Open "EduVers Settings" again, click
echo  "Test connection" and correct the database values.
goto fail
:err_noapp
echo  ERROR: EduVers was not found in %APP%
goto fail
:err_noenv
echo  ERROR: EduVers is not installed yet. Run Install-EduVers.bat from the handover package first.
goto fail
:err_noform
echo  ERROR: the settings form is missing: %APP%\handover\setup\index.php
goto fail
:err_nophp
echo  ERROR: PHP 8.3 or newer was not found in %LARAGON%\bin\php
goto fail
:fail
echo.
if not "%MODE%"=="install" pause
exit /b 1
