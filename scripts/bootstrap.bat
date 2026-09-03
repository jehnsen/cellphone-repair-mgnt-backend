@echo off
REM First-time setup on a fresh machine. Double-click this.
REM
REM   Needs installed first: Git, PHP 8.3+ & Composer, Node.js, Laragon.
REM   Then this clones both repos into %USERPROFILE%\apps and runs dev-up.ps1.
REM
REM   bootstrap.bat                 -> clone + full setup at http://cp-repair-mgnt-app/
REM   bootstrap.bat -Root D:\projects
REM   bootstrap.bat -Demo           -> also load demo data
REM
REM A one-time UAC prompt appears (adds the cp-repair-mgnt-app hosts entry).
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0bootstrap.ps1" %*
set EC=%ERRORLEVEL%
echo.
pause
exit /b %EC%
