@echo off
rem Lab 50, practical work 1: RabbitMQ producers. Logic in scripts\rabbitmq.ps1
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\rabbitmq.ps1"
pause
