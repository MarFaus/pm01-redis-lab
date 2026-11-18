@echo off
rem Stops Redis started by start.bat (Docker container or portable redis-server).
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\stop.ps1"
