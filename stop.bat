@echo off
rem Останавливает Redis, который запустил start.bat (контейнер Docker или переносной).
chcp 65001 >nul
setlocal
cd /d "%~dp0"

set "DOCKER="
for /f "delims=" %%D in ('where docker 2^>nul') do if not defined DOCKER set "DOCKER=%%D"
if not defined DOCKER if exist "%ProgramFiles%\Docker\Dockeresourcesin\docker.exe" set "DOCKER=%ProgramFiles%\Docker\Dockeresourcesin\docker.exe"

if defined DOCKER (
    "%DOCKER%" stop redis-learning >nul 2>&1 && echo Контейнер redis-learning остановлен. Данные останутся до следующего start.bat.
)

tasklist /fi "imagename eq redis-server.exe" 2>nul | find /i "redis-server.exe" >nul && (
    taskkill /im redis-server.exe /f >nul
    echo Переносной Redis остановлен.
)

echo Готово.
timeout /t 3 >nul
