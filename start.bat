@echo off
rem ======================================================================
rem  Касса 6379 - запуск одним кликом.
rem  Сам проверяет и при необходимости докачивает: PHP 8.3, Composer,
rem  зависимости, .env, базу SQLite и Redis (Docker или переносной).
rem  Всё скачанное кладётся в папку .tools рядом с проектом.
rem ======================================================================
chcp 65001 >nul
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Касса 6379

set "ROOT=%~dp0"
set "TOOLS=%ROOT%.tools"
set "CHECK=scripts\check.php"
set "DOCKER_CONTAINER=redis-learning"
set "PHP_URL=https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip"
set "REDIS_URL=https://github.com/tporadowski/redis/releases/download/v5.0.14.1/Redis-x64-5.0.14.1.zip"
set "COMPOSER_URL=https://getcomposer.org/download/latest-stable/composer.phar"
set "VCREDIST_URL=https://aka.ms/vs/17/release/vc_redist.x64.exe"

echo.
echo   Касса 6379: афиша и лаборатория Redis
echo   ------------------------------------------------------------
echo.

if not exist "%TOOLS%" mkdir "%TOOLS%"

where curl >nul 2>&1 || (
    echo [ошибка] В системе нет curl.exe. Нужна Windows 10 версии 1803 или новее.
    goto :fail
)

rem ---------------------------------------------------------------- PHP
echo [1/6] PHP
set "PHP="
if exist "%TOOLS%\php\php.exe" (
    set "PHP=%TOOLS%\php\php.exe"
) else (
    for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP set "PHP=%%P"
)

if defined PHP (
    "!PHP!" "%CHECK%" php >nul 2>&1 || (
        echo       Найден !PHP!, но он не подходит:
        "!PHP!" "%CHECK%" php
        set "PHP="
    )
)

if not defined PHP (
    echo       Скачиваю переносной PHP 8.3, около 35 МБ...
    curl -fL --progress-bar -o "%TOOLS%\php.zip" "%PHP_URL%" || goto :fail_download
    if exist "%TOOLS%\php" rmdir /s /q "%TOOLS%\php"
    mkdir "%TOOLS%\php"
    tar -xf "%TOOLS%\php.zip" -C "%TOOLS%\php" || goto :fail
    del "%TOOLS%\php.zip"
    copy /y "%TOOLS%\php\php.ini-development" "%TOOLS%\php\php.ini" >nul
    set "PHP=%TOOLS%\php\php.exe"

    "!PHP!" -v >nul 2>&1 || call :install_vcredist
    "!PHP!" "%CHECK%" ini "%TOOLS%\php\php.ini" || goto :fail
    "!PHP!" "%CHECK%" php >nul || (
        "!PHP!" "%CHECK%" php
        goto :fail
    )
)
"!PHP!" "%CHECK%" php > "%TOOLS%\out.txt"
set /p PHPVER=<"%TOOLS%\out.txt"
echo       PHP !PHPVER!: !PHP!

rem ----------------------------------------------------------- Composer
echo [2/6] Composer и зависимости
set "COMPOSER="
where composer >nul 2>&1 && set "COMPOSER=composer"
if not defined COMPOSER (
    if not exist "%TOOLS%\composer.phar" (
        echo       Скачиваю composer.phar...
        curl -fsSL -o "%TOOLS%\composer.phar" "%COMPOSER_URL%" || goto :fail_download
    )
    set COMPOSER="!PHP!" "%TOOLS%\composer.phar"
)

if not exist "vendor\autoload.php" (
    echo       Первая установка пакетов, это займёт минуту...
)
call !COMPOSER! install --no-interaction --prefer-dist --no-progress || (
    echo [ошибка] composer install завершился с ошибкой. Проверьте интернет и запустите start.bat ещё раз.
    goto :fail
)
echo       vendor в порядке

rem ---------------------------------------------------------------- .env
echo [3/6] Настройки .env
if exist ".env" (
    findstr /c:"REDIS_LAB_DB" ".env" >nul || (
        echo       Старый .env от другого проекта: сохраняю его как .env.backup и создаю новый
        move /y ".env" ".env.backup" >nul
    )
)
if not exist ".env" (
    copy /y ".env.example" ".env" >nul
    "!PHP!" artisan key:generate --force --no-interaction >nul || goto :fail
    echo       .env создан из .env.example
) else (
    echo       .env на месте
)

rem ---------------------------------------------------------------- База
echo [4/6] База данных SQLite
if not exist "database\database.sqlite" type nul > "database\database.sqlite"
"!PHP!" artisan migrate --force --no-interaction >nul || goto :fail
"!PHP!" artisan kassa:seed-if-empty || goto :fail

rem --------------------------------------------------------------- Redis
echo [5/6] Redis на 127.0.0.1:6379
"!PHP!" "%CHECK%" port 6379 && (
    echo       Redis уже запущен
    goto :redis_ok
)

call :find_docker
if defined DOCKER (
    call :start_redis_docker
"%DOCKER%" info >nul 2>&1 && goto :docker_ready
if not exist "%ProgramFiles%\Docker\Docker\Docker Desktop.exe" exit /b 1
echo       Запускаю Docker Desktop. Если он попросит принять соглашение, примите его в окне Docker.
start "" "%ProgramFiles%\Docker\Docker\Docker Desktop.exe"
set /a TRIES=0
:wait_docker
timeout /t 3 /nobreak >nul
"%DOCKER%" info >nul 2>&1 && goto :docker_ready
set /a TRIES+=1
if %TRIES% lss 40 goto :wait_docker
echo       Docker Desktop не запустился за 2 минуты
exit /b 1
:docker_ready
    set /a TRIES+=1
    if !TRIES! lss 40 goto :wait_docker
    echo       Docker Desktop не запустился за 2 минуты
    exit /b 1
)
:docker_ready
"%DOCKER%" start %DOCKER_CONTAINER% >nul 2>&1 && (
    echo       Контейнер %DOCKER_CONTAINER% запущен
    exit /b 0
)
echo       Создаю контейнер %DOCKER_CONTAINER% из образа redis:7, первый раз скачается около 40 МБ...
"%DOCKER%" run -d --name %DOCKER_CONTAINER% -p 6379:6379 redis:7 >nul || exit /b 1
echo       Контейнер %DOCKER_CONTAINER% создан
exit /b 0

:start_redis_portable
if not exist "%TOOLS%\redis\redis-server.exe" (
    echo       Скачиваю переносной Redis для Windows...
    curl -fL --progress-bar -o "%TOOLS%\redis.zip" "%REDIS_URL%" || exit /b 1
    mkdir "%TOOLS%\redis" 2>nul
    tar -xf "%TOOLS%\redis.zip" -C "%TOOLS%\redis" || exit /b 1
    del "%TOOLS%\redis.zip"
)
echo       Запускаю переносной Redis в свёрнутом окне
start "Redis 6379" /min "%TOOLS%\redis\redis-server.exe" --port 6379 --bind 127.0.0.1 --dir "%TOOLS%\redis"
exit /b 0

:install_docker
where winget >nul 2>&1 || (
    echo       winget не найден, Docker придётся поставить вручную: https://www.docker.com/products/docker-desktop/
    exit /b 0
)
echo       Устанавливаю Docker Desktop. Подтвердите окно UAC, если оно появится...
winget install --id Docker.DockerDesktop -e --accept-package-agreements --accept-source-agreements --silent
exit /b 0

:install_vcredist
echo       PHP не стартует: скорее всего, нет Microsoft Visual C++ Runtime. Скачиваю установщик...
curl -fL --progress-bar -o "%TOOLS%\vc_redist.x64.exe" "%VCREDIST_URL%" || exit /b 1
echo       Устанавливаю. Подтвердите окно UAC...
"%TOOLS%\vc_redist.x64.exe" /install /passive /norestart
exit /b 0

:fail_download
echo [ошибка] Не удалось скачать файл. Проверьте интернет или прокси и запустите start.bat ещё раз.

:fail
echo.
echo   Запуск остановлен. Текст ошибки выше.
pause
exit /b 1
