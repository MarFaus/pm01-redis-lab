# Задания 1 и 2: последовательное и конкурентное выполнение.
$ErrorActionPreference = 'Continue'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $Root
[Console]::OutputEncoding = [Text.Encoding]::UTF8

$php = $null
foreach ($p in @((Join-Path $Root '.tools\php\php.exe'), (Join-Path $env:PUBLIC 'kassa6379-tools\php\php.exe'))) {
    if (Test-Path -LiteralPath $p) { $php = $p; break }
}
if (-not $php) { $cmd = Get-Command php -ErrorAction SilentlyContinue; if ($cmd) { $php = $cmd.Source } }
if (-not $php) { Write-Host 'PHP не найден. Сначала запустите start.bat, он скачает PHP.' -ForegroundColor Red; exit 1 }

Write-Host ''
Write-Host '=== Задание 1. php tasks\sequential.php ===' -ForegroundColor Cyan
& $php tasks\sequential.php

Write-Host ''
Write-Host '=== Задание 2. php tasks\concurrent.php (pcntl_fork) ===' -ForegroundColor Cyan
$docker = Get-Command docker.exe -ErrorAction SilentlyContinue
$ok = $false
if ($docker) {
    & docker info *> $null
    if ($LASTEXITCODE -eq 0) {
        Write-Host 'pcntl_fork() есть только в Linux, запускаю в контейнере php:8.3-cli.'
        Write-Host 'В первый раз Docker скачает образ и соберёт расширение pcntl, это около минуты...'
        & docker run --rm -v "${Root}\tasks:/app" -w /app php:8.3-cli sh -c 'docker-php-ext-install pcntl >/dev/null 2>&1 && php concurrent.php'
        $ok = ($LASTEXITCODE -eq 0)
    }
}
if (-not $ok) {
    Write-Host 'Docker не запущен, поэтому вариант для Windows: tasks\concurrent-windows.php (три процесса через proc_open).'
    & $php tasks\concurrent-windows.php
}
Write-Host ''
Write-Host 'Сравнение: последовательно около 6 с, конкурентно около 2 с.' -ForegroundColor Green
