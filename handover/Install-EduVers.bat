@echo off
rem ======================================================================
rem  EduVers - Installer (run on the CLIENT's PC or laptop)
rem
rem  Before running: install Laragon (https://laragon.org/download/) in
rem  C:\laragon, open it and click "Start All". Extract the whole zip
rem  first - do not run this file from inside the zip.
rem
rem  What it does:
rem    1. copies EduVers to the Laragon "www\eduvers" folder
rem    2. adds the PHP version EduVers needs to Laragon (if it is missing)
rem    3. opens a form in the browser to fill in the database and email settings
rem    4. creates the database and loads the data
rem    5. installs the code editing tools (npm install), if Node.js is present
rem    6. puts "Start EduVers", "Edit EduVers" and "EduVers Settings" on the Desktop
rem
rem  It is safe to run again: existing settings and existing data are kept.
rem ======================================================================
setlocal EnableExtensions
title EduVers - Installer

set "PKG=%~dp0"
if "%PKG:~-1%"=="\" set "PKG=%PKG:~0,-1%"
set "CNF=%TEMP%\eduvers-install-%RANDOM%.cnf"
set "DBVARS=%TEMP%\eduvers-install-%RANDOM%.cmd"
set "TMPOUT=%TEMP%\eduvers-install-%RANDOM%.txt"

echo.
echo  EduVers - Installer
echo  ===================
echo.

if not exist "%PKG%\eduvers\artisan" goto err_notextracted

rem ---------------------------------------------------------------- 1
echo [1/8] Looking for Laragon...
set "LARAGON="
if exist "C:\laragon\laragon.exe" set "LARAGON=C:\laragon"
if not defined LARAGON if exist "D:\laragon\laragon.exe" set "LARAGON=D:\laragon"
if not defined LARAGON if exist "E:\laragon\laragon.exe" set "LARAGON=E:\laragon"
if not defined LARAGON goto err_nolaragon
echo       Found %LARAGON%
set "APP=%LARAGON%\www\eduvers"

rem ---------------------------------------------------------------- 2
echo.
echo [2/8] Preparing PHP...
set "PHP="
rem Prefer the PHP that came with this package: it is the version EduVers was built with.
for /d %%D in ("%PKG%\php\*") do call :usebundled "%%~fD" "%%~nxD"
if not defined PHP for /d %%D in ("%LARAGON%\bin\php\*") do call :checkphp "%%~fD\php.exe"
if not defined PHP goto err_nophp
"%PHP%" -r "exit(extension_loaded('pdo_mysql') && extension_loaded('mbstring') && extension_loaded('openssl') && extension_loaded('fileinfo') && extension_loaded('intl') ? 0 : 1);" >nul 2>nul
if errorlevel 1 goto err_phpext
echo       Using %PHP%

set "MYSQLBIN="
for /d %%D in ("%LARAGON%\bin\mysql\*") do if exist "%%~fD\bin\mysql.exe" set "MYSQLBIN=%%~fD\bin"
if not defined MYSQLBIN goto err_nomysql
set "MYSQL=%MYSQLBIN%\mysql.exe"

rem ---------------------------------------------------------------- 3
echo.
echo [3/8] Copying EduVers to %APP% ...
if not exist "%APP%\artisan" goto copy_files
echo.
echo       EduVers is already installed in that folder.
echo       Updating replaces the program files. Your settings, uploaded
echo       files and database are kept.
choice /c YN /n /m "      Update it now? [Y/N] "
if errorlevel 2 goto cancelled
:copy_files
robocopy "%PKG%\eduvers" "%APP%" /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP
if errorlevel 8 goto err_copy
for %%D in ("storage\logs" "storage\framework\sessions" "storage\framework\views" "storage\framework\cache\data" "storage\app\private" "storage\app\public" "bootstrap\cache") do if not exist "%APP%\%%~D" mkdir "%APP%\%%~D"
for %%F in (config.php routes-v7.php events.php) do if exist "%APP%\bootstrap\cache\%%F" del /q "%APP%\bootstrap\cache\%%F"
if exist "%APP%\public\hot" del /q "%APP%\public\hot"
echo       Done.

rem ---------------------------------------------------------------- 4
echo.
echo [4/8] Settings...
pushd "%APP%"
set "DBTRIES=0"
set "SAVEDMARK=%APP%\storage\app\eduvers-setup-saved.txt"
if not exist "%APP%\.env" goto env_create
if exist "%SAVEDMARK%" goto env_exists
echo       The settings form was not completed last time, so it opens again.
goto env_form
:env_create
copy /y "%APP%\.env.example" "%APP%\.env" >nul
if errorlevel 1 goto err_env
set "EDUVERS_ENV=%APP%\.env"
powershell -NoProfile -Command "$p=$env:EDUVERS_ENV; $t=[IO.File]::ReadAllLines($p); $t=$t -replace '^APP_DEBUG=.*','APP_DEBUG=false' -replace '^APP_URL=.*','APP_URL=http://127.0.0.1:8000' -replace '^QUEUE_CONNECTION=.*','QUEUE_CONNECTION=sync'; [IO.File]::WriteAllLines($p,[string[]]$t)"
if errorlevel 1 goto err_env
"%PHP%" artisan key:generate --force
if errorlevel 1 goto err_artisan
:env_form
echo.
echo       A form opens in your web browser. Fill in the database and email
echo       settings there. If Laragon is on this computer, the database values
echo       are already filled in: click "Test connection", then "Save settings".
call "%APP%\handover\Setup-EduVers.bat" install
if errorlevel 1 goto err_setup
if not exist "%SAVEDMARK%" goto err_setup
goto env_done
:env_exists
echo       Kept the existing settings. (Change them later with "EduVers Settings" on the Desktop.)
:env_done

rem ---------------------------------------------------------------- 5
echo.
echo [5/8] Connecting to the database...
:db_connect
"%PHP%" "%APP%\handover\setup\index.php" export-db "%CNF%" "%DBVARS%"
if errorlevel 1 goto err_export
call "%DBVARS%"
if not "%DB_LOCAL%"=="1" goto db_login

set "TRIES=0"
:mysql_wait
"%MYSQLBIN%\mysqladmin.exe" --defaults-extra-file="%CNF%" --protocol=TCP --connect-timeout=3 ping >nul 2>nul
if not errorlevel 1 goto db_login
set /a TRIES+=1
if %TRIES% GTR 10 goto err_mysqldown
if %TRIES% EQU 1 start "" "%LARAGON%\laragon.exe"
echo.
echo       MySQL is not running yet.
echo       In the Laragon window, click "Start All". When it shows MySQL as started,
echo       come back to this window and press any key.
pause >nul
goto mysql_wait

:db_login
"%MYSQL%" --defaults-extra-file="%CNF%" -e "SELECT 1" >nul 2>"%TMPOUT%"
if not errorlevel 1 goto db_ok
set /a DBTRIES+=1
if %DBTRIES% GTR 3 goto err_dblogin
echo.
echo       The database did not accept the saved settings:
type "%TMPOUT%"
echo.
echo       The settings form opens again. Correct the database values,
echo       click "Test connection" until it is green, then "Save settings".
echo       Press any key to open the form.
pause >nul
call "%APP%\handover\Setup-EduVers.bat" install
if errorlevel 1 goto err_setup
goto db_connect
:db_ok
echo       Connected to %DB_HOST% (database "%DB_DATABASE%").

rem ---------------------------------------------------------------- 6
echo.
echo [6/8] Setting up the database...
"%MYSQL%" --defaults-extra-file="%CNF%" -e "CREATE DATABASE IF NOT EXISTS %DB_DATABASE% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" >nul 2>nul
"%MYSQL%" --defaults-extra-file="%CNF%" %DB_DATABASE% -e "SELECT 1" >nul 2>"%TMPOUT%"
if errorlevel 1 goto err_dbopen
set "TABLES=0"
"%MYSQL%" --defaults-extra-file="%CNF%" -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='%DB_DATABASE%'" >"%TMPOUT%" 2>nul
set /p TABLES=<"%TMPOUT%"
set "SEEDED=0"
if not "%TABLES%"=="0" goto db_keep
if not exist "%PKG%\database\eduvers.sql" goto db_fresh

echo       Loading the data...
"%MYSQL%" --defaults-extra-file="%CNF%" --default-character-set=utf8mb4 %DB_DATABASE% <"%PKG%\database\eduvers.sql"
if errorlevel 1 goto err_import
rem Old sign-in sessions, caches and queued emails from the other PC are not needed here
"%MYSQL%" --defaults-extra-file="%CNF%" %DB_DATABASE% -e "DELETE FROM sessions; DELETE FROM cache; DELETE FROM cache_locks; DELETE FROM jobs; DELETE FROM failed_jobs;" >nul 2>nul
goto db_migrate

:db_fresh
echo       No data file in this package: creating an empty system with demo accounts.
set "SEEDED=1"
goto db_migrate

:db_keep
echo       The database already has data: keeping it.

:db_migrate
"%PHP%" artisan migrate --force
if errorlevel 1 goto err_artisan
if "%SEEDED%"=="1" "%PHP%" artisan db:seed --force
if "%SEEDED%"=="1" if errorlevel 1 goto err_artisan
"%PHP%" artisan optimize:clear >nul 2>nul
echo       Done.

rem ---------------------------------------------------------------- 7
echo.
echo [7/8] Preparing the code editing tools (for "Edit EduVers")...
rem Optional: needs Node.js 20.19+ or 22.12+ and internet. EduVers runs without it.
set "NODEDIR="
for /f "delims=" %%P in ('where node 2^>nul') do call :checknode "%%~dpP."
for /d %%D in ("%LARAGON%\bin\nodejs\*") do call :checknode "%%~fD"
if not defined NODEDIR goto npm_nonode
if exist "%APP%\node_modules\.bin\vite.cmd" goto npm_have
set "PATH=%NODEDIR%;%PATH%"
call npm install --no-audit --no-fund
if errorlevel 1 goto npm_failed
echo       Done.
goto npm_done
:npm_have
echo       Already installed.
goto npm_done
:npm_failed
echo       Skipped: npm install failed (no internet?). EduVers works anyway.
echo       "Edit EduVers" tries again the next time it opens.
goto npm_done
:npm_nonode
echo       Skipped: Node.js 20.19 or newer is not installed. EduVers works anyway.
echo       To change styles later, install the LTS version from https://nodejs.org/
:npm_done

rem ---------------------------------------------------------------- 8
echo.
echo [8/8] Creating the Desktop shortcuts...
set "EDUVERS_APP=%APP%"
powershell -NoProfile -Command "$d=[Environment]::GetFolderPath('Desktop'); $w=New-Object -ComObject WScript.Shell; foreach ($i in @(@('Start EduVers','Start-EduVers.bat','Start the EduVers Learning Management System'),@('Edit EduVers','Edit-EduVers.bat','Open the EduVers code for editing, with live styles'),@('EduVers Settings','Setup-EduVers.bat','Change the EduVers database and email settings'))) { $s=$w.CreateShortcut((Join-Path $d ($i[0] + '.lnk'))); $s.TargetPath=(Join-Path $env:EDUVERS_APP ('handover\' + $i[1])); $s.WorkingDirectory=$env:EDUVERS_APP; $s.Description=$i[2]; $s.Save() }" >nul 2>nul
if errorlevel 1 echo       Could not create the shortcuts. Start EduVers with: %APP%\handover\Start-EduVers.bat
if not errorlevel 1 echo       Done.

popd
del /q "%CNF%" "%DBVARS%" "%TMPOUT%" >nul 2>nul

echo.
echo  ===================
echo  EduVers is installed - finished.
echo  ===================
echo.
echo  On the Desktop:
echo     "Start EduVers"     opens EduVers  (http://127.0.0.1:8000)
echo     "Edit EduVers"      opens the code in VS Code with live styles (for changing the system)
echo     "EduVers Settings"  changes the database and email settings
echo.
echo  Installed in : %APP%
echo  Manual       : %APP%\README.md
echo.
if "%SEEDED%"=="1" echo  Demo sign-in accounts (password: password) - change them after signing in:
if "%SEEDED%"=="1" echo      developer@eduvers.test   teacher@eduvers.test   student@eduvers.test
if "%SEEDED%"=="1" echo.
if "%SEEDED%"=="0" echo  Sign in with the accounts your developer gave you.
if "%SEEDED%"=="0" echo.
choice /c YN /n /m " Start EduVers now? [Y/N] "
if errorlevel 2 goto done
start "" "%APP%\handover\Start-EduVers.bat"
:done
exit /b 0

rem ======================================================================
:usebundled
rem First argument: a PHP folder inside the package. Second: its folder name.
if defined PHP exit /b 0
if not exist "%~1\php.exe" exit /b 0
if exist "%LARAGON%\bin\php\%~2\php.exe" goto usebundled_check
echo       Adding %~2 to Laragon...
robocopy "%~1" "%LARAGON%\bin\php\%~2" /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP >nul
rem Files from a downloaded zip can be marked "blocked" by Windows; clear that for PHP.
set "EDUVERS_PHPDIR=%LARAGON%\bin\php\%~2"
powershell -NoProfile -Command "Get-ChildItem -LiteralPath $env:EDUVERS_PHPDIR -Recurse -File | Unblock-File" >nul 2>nul
:usebundled_check
call :checkphp "%LARAGON%\bin\php\%~2\php.exe"
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

:checkphp
rem Argument: full path to a php.exe. Sets PHP when it is version 8.3 or newer.
if defined PHP exit /b 0
if not exist "%~1" exit /b 0
"%~1" -r "exit(PHP_VERSION_ID >= 80300 ? 0 : 1);" >nul 2>nul
if errorlevel 1 exit /b 0
set "PHP=%~1"
exit /b 0

:err_notextracted
echo  ERROR: the "eduvers" folder is not next to this file.
echo.
echo  Extract the WHOLE zip first (right-click the zip, "Extract All..."),
echo  then open the extracted folder and double-click Install-EduVers.bat there.
goto fail
:err_nolaragon
echo  Laragon is not installed in C:\laragon.
echo.
echo  1. A download page will open now. Install Laragon and keep the folder C:\laragon.
echo  2. Open Laragon and click "Start All".
echo  3. Double-click Install-EduVers.bat again.
start "" "https://laragon.org/download/"
goto fail
:err_nophp
echo  ERROR: no working PHP 8.3 or newer was found.
echo.
echo  This package's "php" folder is missing or its PHP cannot start on this PC.
echo  If Windows showed a message about VCRUNTIME140.dll, install the
echo  Microsoft Visual C++ Redistributable x64 from Microsoft, then run this file again.
goto fail
:err_phpext
echo  ERROR: this PHP is missing an extension EduVers needs
echo         (pdo_mysql, mbstring, openssl, fileinfo or intl).
echo         PHP used: %PHP%
echo.
echo  In Laragon: Menu - PHP - Extensions, tick the ones listed above,
echo  then run this file again.
goto fail
:err_nomysql
echo  ERROR: MySQL was not found in %LARAGON%\bin\mysql.
echo         Install the full version of Laragon (it includes MySQL) and run this file again.
goto fail
:err_copy
echo  ERROR: copying the files failed. Close any program using %APP% and try again.
goto fail
:err_env
popd
echo  ERROR: could not create %APP%\.env
goto fail
:err_setup
popd
echo.
echo  The settings form was not saved, so the installation stopped here.
echo  Run Install-EduVers.bat again when you are ready.
goto fail
:err_export
popd
echo  ERROR: the saved settings could not be read. The message above says why.
goto fail
:err_mysqldown
popd
echo  ERROR: MySQL is still not running. Open Laragon, click "Start All",
echo         wait until MySQL shows as started, then run this file again.
goto fail
:err_dblogin
popd
echo  ERROR: the database still refuses the settings. Ask whoever manages the
echo         database for the correct username and password, then run this file again.
goto fail
:err_dbopen
popd
echo  ERROR: the database "%DB_DATABASE%" could not be created or opened:
type "%TMPOUT%"
echo         Ask whoever manages the database to create it, then run this file again.
goto fail
:err_import
popd
echo  ERROR: loading the data failed. The message above this line says why.
rem On this computer the database was empty before this run, so remove the half-loaded copy.
if "%DB_LOCAL%"=="1" "%MYSQL%" --defaults-extra-file="%CNF%" -e "DROP DATABASE IF EXISTS %DB_DATABASE%" >nul 2>nul
if "%DB_LOCAL%"=="1" echo         The half-loaded database was removed. Fix the problem and run this file again.
if not "%DB_LOCAL%"=="1" echo         Empty the database "%DB_DATABASE%" (delete its tables), then run this file again.
goto fail
:err_artisan
popd
echo  ERROR: a setup command failed. The message above this line says why.
echo         Nothing was deleted. Fix the problem and run this file again.
goto fail
:cancelled
del /q "%CNF%" "%DBVARS%" "%TMPOUT%" >nul 2>nul
echo.
echo  Cancelled. Nothing was changed.
echo.
pause
exit /b 0
:fail
del /q "%CNF%" "%DBVARS%" "%TMPOUT%" >nul 2>nul
echo.
echo  EduVers was NOT installed completely.
echo.
pause
exit /b 1
