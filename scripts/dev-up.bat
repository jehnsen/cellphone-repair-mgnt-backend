@echo off
REM Double-click launcher for scripts\dev-up.ps1
REM   dev-up.bat            -> full setup; serves at http://cp-repair-mgnt-app/
REM                           (first run auto-seeds staff logins + catalog)
REM   dev-up.bat -Demo      -> also load the demo dataset (customers, tickets, sales)
REM   dev-up.bat -Fresh     -> wipe DB + reseed + clean production build
REM   dev-up.bat -Dev       -> frontend hot-reload instead of a build
REM   dev-up.bat -NoProxy   -> skip hosts/Apache; use http://localhost:3000
REM   dev-up.bat -Stop      -> kill the windows this launcher started
REM
REM First run adds "127.0.0.1 cp-repair-mgnt-app" to the Windows hosts file
REM and will pop a UAC prompt for that one step - accept it.
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0dev-up.ps1" %*
set EC=%ERRORLEVEL%
if not "%~1"=="-Stop" (
  echo.
  pause
)
exit /b %EC%
