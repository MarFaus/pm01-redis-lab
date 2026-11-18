# Останавливает Redis, который запустил start.bat (контейнер Docker или переносной).
[Console]::OutputEncoding = [Text.Encoding]::UTF8

$docker = Get-Command docker.exe -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Source
if (-not $docker) {
    $path = Join-Path $env:ProgramFiles 'Docker\Docker\resources\bin\docker.exe'
    if (Test-Path -LiteralPath $path) { $docker = $path }
}
if ($docker) {
    & $docker stop redis-learning *> $null
    if ($LASTEXITCODE -eq 0) { Write-Host 'Контейнер redis-learning остановлен. Данные сохранятся до следующего start.bat.' }
}

$portable = Get-Process redis-server -ErrorAction SilentlyContinue
if ($portable) {
    $portable | Stop-Process -Force
    Write-Host 'Переносной Redis остановлен.'
}

Write-Host 'Готово.'
Start-Sleep -Seconds 2
