@echo off
rem ======================================================================
rem  EduVers - Make handover package (run on the DEVELOPER's PC)
rem
rem  Double-click this file. It creates, in C:\EduVers-Handover :
rem     EduVers-(date-time)\          a clean copy for the client
rem        Install-EduVers.bat        one-click installer for the client
rem        MANUAL-INSTALL.txt         the same steps by hand
rem        READ-ME-FIRST.txt
rem        eduvers\                   the application (no .env, no secrets)
rem        database\eduvers.sql       a copy of your LOCAL database
rem        php\                       the PHP version EduVers needs
rem     EduVers-(date-time).zip       the same folder, zipped
rem
rem  Your project is not changed, except that public\build is rebuilt
rem  (npm run build) so the package has the latest styles and scripts.
rem ======================================================================
setlocal EnableExtensions
title EduVers - Make handover package

set "HERE=%~dp0"
for %%I in ("%HERE%..") do set "SRC=%%~fI"
for %%I in ("%SRC%\..\..") do set "LARAGON=%%~fI"
if not exist "%LARAGON%\bin" set "LARAGON=C:\laragon"
set "OUTROOT=C:\EduVers-Handover"

echo.
echo  EduVers - Make handover package
echo  ================================
echo  Project : %SRC%
echo  Laragon : %LARAGON%
echo.

if not exist "%SRC%\artisan" goto err_noproject
if not exist "%SRC%\vendor\autoload.php" goto err_novendor
if not exist "%HERE%Install-EduVers.bat" goto err_noinstaller

set "STAMP="
for /f %%I in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss"') do set "STAMP=%%I"
if not defined STAMP set "STAMP=package-%RANDOM%"
set "NAME=EduVers-%STAMP%"
set "OUT=%OUTROOT%\%NAME%"
set "ZIP=%OUTROOT%\%NAME%.zip"

rem ---------------------------------------------------------------- 1
echo [1/6] Building the styles and scripts (npm run build)...
for /d %%D in ("%LARAGON%\bin\nodejs\*") do if exist "%%~fD\npm.cmd" set "NODEDIR=%%~fD"
if defined NODEDIR set "PATH=%NODEDIR%;%PATH%"
where npm >nul 2>nul
if errorlevel 1 goto build_skipped
if not exist "%SRC%\node_modules" goto build_skipped
pushd "%SRC%"
call npm run build
if errorlevel 1 echo       WARNING: the build failed. The package will use the last build in public\build.
popd
goto build_done
:build_skipped
echo       Skipped: npm or node_modules was not found.
echo       The package will use the existing public\build folder - make sure it is up to date.
:build_done
if not exist "%SRC%\public\build\manifest.json" goto err_nobuild

rem ---------------------------------------------------------------- 2
echo.
echo [2/6] Copying the application (without secrets, logs or caches)...
mkdir "%OUT%" 2>nul
if not exist "%OUT%" goto err_nofolder

robocopy "%SRC%" "%OUT%\eduvers" /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP ^
 /XD "%SRC%\node_modules" "%SRC%\.git" "%SRC%\Claude outputs" "%SRC%\storage\logs" "%SRC%\storage\framework\sessions" "%SRC%\storage\framework\views" "%SRC%\storage\framework\cache" "%SRC%\storage\framework\testing" ^
 /XF "%SRC%\.env" "%SRC%\eduvers.sql" "%SRC%\eduvers.zip" "%SRC%\.phpunit.result.cache" "%SRC%\public\hot" "%SRC%\bootstrap\cache\config.php" "%SRC%\bootstrap\cache\routes-v7.php" "%SRC%\bootstrap\cache\events.php" "%SRC%\storage\app\eduvers-setup-saved.txt" .env.backup .env.tmp .env.production .env.local
if errorlevel 8 goto err_copy
if exist "%OUT%\eduvers\.env" goto err_envleak
echo       Done.

rem ---------------------------------------------------------------- 3
echo.
echo [3/6] Adding PHP 8.3 or newer (so the client does not have to install it)...
set "PHPDIR="
set "PHPNAME="
for /d %%D in ("%LARAGON%\bin\php\*") do call :checkphp "%%~fD"
if not defined PHPDIR goto php_missing
robocopy "%PHPDIR%" "%OUT%\php\%PHPNAME%" /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP
if errorlevel 8 goto err_copy
echo       Added %PHPNAME%.
goto php_done
:php_missing
echo       WARNING: no PHP 8.3+ found in %LARAGON%\bin\php.
echo       The client's Laragon must then already have PHP 8.3 or newer.
:php_done

rem ---------------------------------------------------------------- 4
echo.
echo [4/6] Exporting your local database...
set "DB_CONNECTION=mysql"
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_DATABASE=eduvers"
set "DB_USERNAME=root"
set "DB_PASSWORD="
if exist "%SRC%\.env" for /f "usebackq tokens=1,* delims==" %%A in (`findstr /b /c:"DB_CONNECTION=" /c:"DB_HOST=" /c:"DB_PORT=" /c:"DB_DATABASE=" /c:"DB_USERNAME=" /c:"DB_PASSWORD=" "%SRC%\.env"`) do set "%%A=%%~B"
set "DUMPED=0"
if /i not "%DB_CONNECTION%"=="mysql" goto dump_notmysql

set "MYSQLBIN="
for /d %%D in ("%LARAGON%\bin\mysql\*") do if exist "%%~fD\bin\mysqldump.exe" set "MYSQLBIN=%%~fD\bin"
if not defined MYSQLBIN for /f "delims=" %%P in ('where mysqldump 2^>nul') do set "MYSQLBIN=%%~dpP."
if not defined MYSQLBIN goto dump_notool

mkdir "%OUT%\database" 2>nul
set "CNF=%TEMP%\eduvers-dump-%RANDOM%.cnf"
>"%CNF%" echo [client]
>>"%CNF%" echo host=%DB_HOST%
>>"%CNF%" echo port=%DB_PORT%
>>"%CNF%" echo user=%DB_USERNAME%
>>"%CNF%" echo password="%DB_PASSWORD%"
"%MYSQLBIN%\mysqldump.exe" --defaults-extra-file="%CNF%" --single-transaction --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 --result-file="%OUT%\database\eduvers.sql" "%DB_DATABASE%"
set "DUMPERR=%ERRORLEVEL%"
del /q "%CNF%" >nul 2>nul
if not "%DUMPERR%"=="0" goto dump_failed
set "DUMPED=1"
echo       Saved database "%DB_DATABASE%" from %DB_HOST%.
goto dump_done

:dump_notmysql
echo       Skipped: .env uses DB_CONNECTION=%DB_CONNECTION%, not mysql.
goto dump_done
:dump_notool
echo       WARNING: mysqldump.exe was not found, so no database is included.
goto dump_done
:dump_failed
if exist "%OUT%\database\eduvers.sql" del /q "%OUT%\database\eduvers.sql"
echo       WARNING: the export failed (is MySQL running? click "Start All" in Laragon and run this again).
echo       The package is still created, but WITHOUT your data.
:dump_done

rem ---------------------------------------------------------------- 5
echo.
echo [5/6] Adding the installer and instructions...
copy /y "%HERE%Install-EduVers.bat" "%OUT%\Install-EduVers.bat" >nul
if errorlevel 1 goto err_copy
if exist "%HERE%MANUAL-INSTALL.txt" copy /y "%HERE%MANUAL-INSTALL.txt" "%OUT%\MANUAL-INSTALL.txt" >nul

set "TXT=%OUT%\READ-ME-FIRST.txt"
>"%TXT%" echo EduVers - Learning Management System
>>"%TXT%" echo Movers Institute of Technology and Education
>>"%TXT%" echo ============================================
>>"%TXT%" echo.
>>"%TXT%" echo HOW TO INSTALL (Windows 10 or 11)
>>"%TXT%" echo.
>>"%TXT%" echo  1. Install Laragon from https://laragon.org/download/
>>"%TXT%" echo     Keep the default folder C:\laragon.
>>"%TXT%" echo  2. Open Laragon and click "Start All".
>>"%TXT%" echo  3. Extract this whole zip to a normal folder, for example the Desktop.
>>"%TXT%" echo     Do not run the installer from inside the zip.
>>"%TXT%" echo  4. Double-click Install-EduVers.bat.
>>"%TXT%" echo  5. A form opens in your web browser. It asks where the database is and
>>"%TXT%" echo     whether EduVers should send emails. With Laragon on this computer the
>>"%TXT%" echo     database values are already filled in: click "Test connection", then
>>"%TXT%" echo     "Save settings". The installer then continues by itself.
>>"%TXT%" echo  6. Wait until the installer says "finished".
>>"%TXT%" echo.
>>"%TXT%" echo HOW TO OPEN EDUVERS AFTERWARDS
>>"%TXT%" echo.
>>"%TXT%" echo  Double-click "Start EduVers" on the Desktop.
>>"%TXT%" echo  Keep its black window open while you use EduVers; closing it stops EduVers.
>>"%TXT%" echo  The address is http://127.0.0.1:8000
>>"%TXT%" echo.
>>"%TXT%" echo HOW TO EDIT THE CODE
>>"%TXT%" echo.
>>"%TXT%" echo  Double-click "Edit EduVers" on the Desktop. It opens the code in
>>"%TXT%" echo  Visual Studio Code, starts live styles and opens EduVers. When you finish,
>>"%TXT%" echo  press a key in its black window so your style changes are saved.
>>"%TXT%" echo  For changing styles, install Node.js LTS from https://nodejs.org/ first.
>>"%TXT%" echo  For presenting, always use "Start EduVers".
>>"%TXT%" echo.
>>"%TXT%" echo HOW TO CHANGE THE DATABASE OR EMAIL SETTINGS LATER
>>"%TXT%" echo.
>>"%TXT%" echo  Double-click "EduVers Settings" on the Desktop. The same form opens.
>>"%TXT%" echo  After saving, close EduVers and start it again.
>>"%TXT%" echo.
>>"%TXT%" echo WHAT IS IN THIS PACKAGE
>>"%TXT%" echo.
>>"%TXT%" echo  Install-EduVers.bat   the installer
>>"%TXT%" echo  MANUAL-INSTALL.txt    the same steps by hand, if the installer fails
>>"%TXT%" echo  eduvers\              the application (source code)
>>"%TXT%" echo  database\             the data (accounts, sections, lessons, quizzes)
>>"%TXT%" echo  php\                  the PHP version EduVers needs
>>"%TXT%" echo.
>>"%TXT%" echo  The full manual is eduvers\README.md
>>"%TXT%" echo.
>>"%TXT%" echo Package created: %STAMP%
echo       Done.

rem ---------------------------------------------------------------- 6
echo.
echo [6/6] Creating the zip file (this can take a few minutes)...
set "ZIPPED=0"
where tar >nul 2>nul
if errorlevel 1 goto zip_powershell
tar -a -c -f "%ZIP%" -C "%OUTROOT%" "%NAME%"
if errorlevel 1 goto zip_powershell
set "ZIPPED=1"
goto zip_done
:zip_powershell
if exist "%ZIP%" del /q "%ZIP%"
powershell -NoProfile -Command "Compress-Archive -Path '%OUT%' -DestinationPath '%ZIP%' -Force"
if not errorlevel 1 set "ZIPPED=1"
:zip_done

echo.
echo  ================================
echo  FINISHED
echo  ================================
echo  Folder : %OUT%
if "%ZIPPED%"=="1" echo  Zip    : %ZIP%
if "%ZIPPED%"=="0" echo  Zip    : could not be created - right-click the folder and choose "Send to - Compressed folder".
if "%DUMPED%"=="1" echo  Data   : included (your LOCAL database, not the live website's)
if "%DUMPED%"=="0" echo  Data   : NOT included - the client will get an empty system with demo accounts
echo.
echo  Give the client the zip file. They extract it and double-click Install-EduVers.bat.
echo.
if "%ZIPPED%"=="1" explorer /select,"%ZIP%"
if "%ZIPPED%"=="0" explorer "%OUT%"
pause
exit /b 0

rem ======================================================================
:checkphp
if not exist "%~1\php.exe" exit /b 0
"%~1\php.exe" -r "exit(PHP_VERSION_ID >= 80300 ? 0 : 1);" >nul 2>nul
if errorlevel 1 exit /b 0
set "PHPDIR=%~1"
set "PHPNAME=%~nx1"
exit /b 0

:err_noproject
echo  ERROR: this file must stay inside the project's "handover" folder.
echo         "%SRC%\artisan" was not found.
goto fail
:err_novendor
echo  ERROR: the "vendor" folder is missing. Run "composer install" in the project first.
goto fail
:err_noinstaller
echo  ERROR: Install-EduVers.bat is missing from the "handover" folder.
goto fail
:err_nobuild
echo  ERROR: public\build is missing. Open Laragon's Terminal in the project and run:
echo             npm install
echo             npm run build
echo         then run this file again.
goto fail
:err_nofolder
echo  ERROR: could not create %OUT%
goto fail
:err_copy
echo  ERROR: copying files failed. Close any program that has project files open and try again.
goto fail
:err_envleak
echo  ERROR: the .env file ended up in the package. Stopping so your passwords are not shared.
echo         Delete the folder %OUT% and do not send it to anyone.
goto fail
:fail
echo.
echo  The package was NOT completed.
echo.
pause
exit /b 1
