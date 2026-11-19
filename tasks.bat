@echo off
rem Tasks 1 and 2: sequential vs concurrent execution. Logic in scripts\tasks.ps1
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\tasks.ps1"
pause
