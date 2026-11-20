# ЛР 50, практическая работа 1: Producer RabbitMQ.
$ErrorActionPreference = 'Continue'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath (Join-Path $Root 'rabbitmq-producer')
[Console]::OutputEncoding = [Text.Encoding]::UTF8

$php = $null
foreach ($p in @((Join-Path $Root '.tools\php\php.exe'), (Join-Path $env:PUBLIC 'kassa6379-tools\php\php.exe'))) {
    if (Test-Path -LiteralPath $p) { $php = $p; break }
}
if (-not $php) { $cmd = Get-Command php -ErrorAction SilentlyContinue; if ($cmd) { $php = $cmd.Source } }
if (-not $php -or -not (Test-Path -LiteralPath (Join-Path $Root 'vendor\autoload.php'))) {
    Write-Host 'Сначала запустите start.bat: он скачает PHP, библиотеку php-amqplib и поднимет RabbitMQ.' -ForegroundColor Red
    exit 1
}

function Run($title, $file, $argsList) {
    Write-Host ''
    Write-Host "=== $title ===" -ForegroundColor Cyan
    Write-Host "php $file $argsList"
    & $php $file @argsList
}

Run 'Задания 3-4. Producer из методички' 'producer.php' @()
Run 'Доп. задание. 5 событий заказа 201 в events_queue' 'producer-events.php' @('201', '15')
Run 'Повышенная сложность. Direct Exchange portal_exchange' 'producer-exchange.php' @('201')

Write-Host ''
Write-Host 'Сообщения можно посмотреть в панели: http://localhost:15672 (guest / guest),' -ForegroundColor Green
Write-Host 'раздел Queues and Streams, очередь, кнопка Get messages.' -ForegroundColor Green
