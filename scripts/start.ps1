# Касса 6379: запуск одним кликом (вызывается из start.bat).
# Сам проверяет и при необходимости докачивает PHP 8.3, Composer, зависимости,
# .env, базу SQLite и Redis (Docker или переносной). Скачанное лежит в .tools.
# Совместим с Windows PowerShell 5.1, который есть в любой Windows 10/11.

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$OutputEncoding = [Text.Encoding]::UTF8

$Root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $Root
$Host.UI.RawUI.WindowTitle = 'Касса 6379'

function Test-Ascii($path) { return -not ($path -match '[^\x00-\x7F]') }

# php.exe, лежащий в папке с кириллицей, ломает `artisan serve` (дочерний
# php -S получает испорченный путь). Поэтому инструменты кладём в папку на латинице.
$Tools = Join-Path $Root '.tools'
if (-not (Test-Ascii $Tools)) { $Tools = Join-Path $env:PUBLIC 'kassa6379-tools' }
$Check = Join-Path $Root 'scripts\check.php'
$Container = 'redis-learning'
$PhpUrl = 'https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip'
$RedisUrl = 'https://github.com/tporadowski/redis/releases/download/v5.0.14.1/Redis-x64-5.0.14.1.zip'
$ComposerUrl = 'https://getcomposer.org/download/latest-stable/composer.phar'
$VcRedistUrl = 'https://aka.ms/vs/17/release/vc_redist.x64.exe'

function Step($n, $text) { Write-Host "[$n/6] $text" -ForegroundColor Cyan }
function Info($text) { Write-Host "      $text" }
function Fail($text) {
    Write-Host ''
    Write-Host "  [ошибка] $text" -ForegroundColor Red
    Write-Host '  Запуск остановлен.'
    Write-Host ''
    if ($env:KASSA_NONINTERACTIVE -ne '1') { Read-Host '  Нажмите Enter, чтобы закрыть окно' | Out-Null }
    exit 1
}

function Download($url, $out) {
    & curl.exe -fL --progress-bar -o $out $url
    if ($LASTEXITCODE -ne 0) { Fail "Не удалось скачать $url. Проверьте интернет или прокси и запустите start.bat ещё раз." }
}

function Expand($zip, $dir) {
    if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Path $dir | Out-Null }
    & tar.exe -xf $zip -C $dir
    if ($LASTEXITCODE -ne 0) { Expand-Archive -LiteralPath $zip -DestinationPath $dir -Force }
    Remove-Item -LiteralPath $zip -Force
}

function Test-Port($port) {
    $client = New-Object Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect('127.0.0.1', $port, $null, $null)
        return ($async.AsyncWaitHandle.WaitOne(500) -and $client.Connected)
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Wait-Port($port, $seconds) {
    $deadline = (Get-Date).AddSeconds($seconds)
    while ((Get-Date) -lt $deadline) {
        if (Test-Port $port) { return $true }
        Start-Sleep -Milliseconds 500
    }
    return $false
}

function Test-Php($exe) {
    & $exe $Check php *> $null
    return ($LASTEXITCODE -eq 0)
}

function Artisan { & $script:PHP artisan @args }

Write-Host ''
Write-Host '  Касса 6379: афиша и лаборатория Redis' -ForegroundColor White
Write-Host '  ------------------------------------------------------------'
Write-Host ''

if (-not (Get-Command curl.exe -ErrorAction SilentlyContinue)) {
    Fail 'В системе нет curl.exe. Нужна Windows 10 версии 1803 или новее.'
}
if (-not (Test-Path -LiteralPath $Tools)) { New-Item -ItemType Directory -Path $Tools | Out-Null }
if ($Tools -ne (Join-Path $Root '.tools')) { Info "В пути проекта есть кириллица, поэтому скачанные инструменты лежат в $Tools" }

# ------------------------------------------------------------------ PHP
Step 1 'PHP'
$PHP = $null
$LocalPhp = Join-Path $Tools 'php\php.exe'
if (Test-Path -LiteralPath $LocalPhp) {
    $PHP = $LocalPhp
} else {
    $found = Get-Command php.exe -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($found) { $PHP = $found.Source }
}

if ($PHP -and -not (Test-Ascii $PHP)) {
    Info "PHP лежит в папке с не латинскими буквами ($PHP), с ним не работает artisan serve. Беру переносной."
    $PHP = $null
}

if ($PHP -and -not (Test-Php $PHP)) {
    Info "Найден $PHP, но он не подходит:"
    & $PHP $Check php
    $PHP = $null
}

if (-not $PHP) {
    Info 'Скачиваю переносной PHP 8.3, около 35 МБ...'
    $phpDir = Join-Path $Tools 'php'
    if (Test-Path -LiteralPath $phpDir) { Remove-Item -LiteralPath $phpDir -Recurse -Force }
    $zip = Join-Path $Tools 'php.zip'
    Download $PhpUrl $zip
    Expand $zip $phpDir
    Copy-Item -LiteralPath (Join-Path $phpDir 'php.ini-development') -Destination (Join-Path $phpDir 'php.ini') -Force
    $PHP = $LocalPhp

    & $PHP -v *> $null
    if ($LASTEXITCODE -ne 0) {
        Info 'PHP не стартует: нет Microsoft Visual C++ Runtime. Скачиваю установщик...'
        $vc = Join-Path $Tools 'vc_redist.x64.exe'
        Download $VcRedistUrl $vc
        Info 'Устанавливаю. Подтвердите окно UAC...'
        Start-Process -FilePath $vc -ArgumentList '/install', '/passive', '/norestart' -Verb RunAs -Wait
    }

    & $PHP $Check ini (Join-Path $phpDir 'php.ini')
    if (-not (Test-Php $PHP)) {
        & $PHP $Check php
        Fail 'Переносной PHP не прошёл проверку.'
    }
}
$phpVersion = & $PHP $Check php
Info "PHP ${phpVersion}: $PHP"

# ------------------------------------------------------------- Composer
Step 2 'Composer и зависимости'
$ComposerPhar = $null
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    $ComposerPhar = Join-Path $Tools 'composer.phar'
    if (-not (Test-Path -LiteralPath $ComposerPhar)) {
        Info 'Скачиваю composer.phar...'
        Download $ComposerUrl $ComposerPhar
    }
}

if (-not (Test-Path -LiteralPath 'vendor\autoload.php')) { Info 'Первая установка пакетов, это займёт минуту...' }
if ($ComposerPhar) {
    & $PHP $ComposerPhar install --no-interaction --prefer-dist --no-progress
} else {
    & composer install --no-interaction --prefer-dist --no-progress
}
if ($LASTEXITCODE -ne 0) { Fail 'composer install завершился с ошибкой. Проверьте интернет и запустите start.bat ещё раз.' }
Info 'vendor в порядке'

# ----------------------------------------------------------------- .env
Step 3 'Настройки .env'
if ((Test-Path -LiteralPath '.env') -and -not (Select-String -LiteralPath '.env' -Pattern 'REDIS_LAB_DB' -Quiet)) {
    Info 'Старый .env от другой версии: сохраняю его как .env.backup и создаю новый'
    Move-Item -LiteralPath '.env' -Destination '.env.backup' -Force
}
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
    Artisan key:generate --force --no-interaction | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail 'Не удалось сгенерировать APP_KEY.' }
    Info '.env создан из .env.example'
} else {
    Info '.env на месте'
}

# ----------------------------------------------------------------- База
Step 4 'База данных SQLite'
$db = 'database\database.sqlite'
if (-not (Test-Path -LiteralPath $db)) { New-Item -ItemType File -Path $db | Out-Null }
Artisan migrate --force --no-interaction | Out-Null
if ($LASTEXITCODE -ne 0) { Fail 'Миграции не прошли. Подробности покажет команда: php artisan migrate' }
Artisan kassa:seed-if-empty
if ($LASTEXITCODE -ne 0) { Fail 'Не удалось заполнить базу.' }

# ---------------------------------------------------------------- Redis
Step 5 'Redis на 127.0.0.1:6379'

function Find-Docker {
    $cmd = Get-Command docker.exe -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($cmd) { return $cmd.Source }
    $path = Join-Path $env:ProgramFiles 'Docker\Docker\resources\bin\docker.exe'
    if (Test-Path -LiteralPath $path) { return $path }
    return $null
}

function Start-RedisDocker($docker) {
    & $docker info *> $null
    if ($LASTEXITCODE -ne 0) {
        $desktop = Join-Path $env:ProgramFiles 'Docker\Docker\Docker Desktop.exe'
        if (-not (Test-Path -LiteralPath $desktop)) { return $false }
        Info 'Запускаю Docker Desktop. Если он попросит принять соглашение, примите его в окне Docker.'
        Start-Process -FilePath $desktop
        $deadline = (Get-Date).AddMinutes(2)
        do {
            Start-Sleep -Seconds 3
            & $docker info *> $null
        } while ($LASTEXITCODE -ne 0 -and (Get-Date) -lt $deadline)
        if ($LASTEXITCODE -ne 0) {
            Info 'Docker Desktop не запустился за 2 минуты'
            return $false
        }
    }

    & $docker start $Container *> $null
    if ($LASTEXITCODE -eq 0) {
        Info "Контейнер $Container запущен (Docker, redis:7)"
        return $true
    }

    Info "Создаю контейнер $Container из образа redis:7, в первый раз скачается около 40 МБ..."
    & $docker run -d --name $Container -p 6379:6379 redis:7 | Out-Null
    if ($LASTEXITCODE -ne 0) { return $false }
    Info "Контейнер $Container создан"
    return $true
}

function Start-RedisPortable {
    $dir = Join-Path $Tools 'redis'
    $exe = Join-Path $dir 'redis-server.exe'
    if (-not (Test-Path -LiteralPath $exe)) {
        Info 'Скачиваю переносной Redis для Windows, около 5 МБ...'
        $zip = Join-Path $Tools 'redis.zip'
        Download $RedisUrl $zip
        Expand $zip $dir
    }
    Info 'Запускаю переносной Redis в свёрнутом окне'
    Start-Process -FilePath $exe -ArgumentList '--port', '6379', '--bind', '127.0.0.1' -WorkingDirectory $dir -WindowStyle Minimized
}

function Install-Docker {
    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        Info 'winget не найден. Docker можно поставить вручную: https://www.docker.com/products/docker-desktop/'
        return
    }
    Info 'Устанавливаю Docker Desktop. Подтвердите окно UAC, если оно появится...'
    & winget install --id Docker.DockerDesktop -e --accept-package-agreements --accept-source-agreements --silent
}

if (Test-Port 6379) {
    Info 'Redis уже запущен'
} else {
    $docker = if ($env:KASSA_NO_DOCKER -eq '1') { $null } else { Find-Docker }
    $started = $false
    if ($docker) {
        $started = Start-RedisDocker $docker
        if (-not $started) { Info 'С Docker не получилось, беру переносной Redis' }
    } else {
        Info 'Docker не найден.'
        Info '  [1] Установить Docker Desktop через winget. Нужны права администратора.'
        Info '      Сейчас запустится переносной Redis, а со следующего запуска будет Docker, как в методичке.'
        Info '  [2] Только переносной Redis для Windows, без установки.'
        $choice = 2
        if ($env:KASSA_NONINTERACTIVE -ne '1') {
            Write-Host '      Ваш выбор [1/2], через 20 секунд выберу 2: ' -NoNewline
            & choice.exe /c 12 /n /t 20 /d 2 | Out-Null
            $choice = $LASTEXITCODE
            Write-Host $choice
        }
        if ($choice -eq 1) { Install-Docker }
    }
    if (-not $started) { Start-RedisPortable }

    if (-not (Wait-Port 6379 20)) { Fail 'Redis так и не ответил на порту 6379.' }
}

# --------------------------------------------------------------- Сервер
Step 6 'Запуск сайта'
Artisan config:clear | Out-Null
Artisan cache:clear *> $null

$port = 8000
while ((Test-Port $port) -and $port -lt 8100) { $port++ }
$url = "http://127.0.0.1:$port"

Write-Host ''
Write-Host '  ------------------------------------------------------------'
Write-Host "  Готово. Сайт: $url" -ForegroundColor Green
Write-Host "  Лаборатория: $url/lab"
Write-Host '  Остановить сайт: закройте это окно или нажмите Ctrl+C.'
Write-Host '  Остановить Redis: stop.bat'
Write-Host '  ------------------------------------------------------------'
Write-Host ''

if ($env:KASSA_NONINTERACTIVE -ne '1') {
    Start-Job -ScriptBlock { param($u) Start-Sleep -Seconds 2; Start-Process $u } -ArgumentList $url | Out-Null
}
Artisan serve --host=127.0.0.1 --port=$port
