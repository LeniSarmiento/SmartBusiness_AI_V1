param()
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $taskRoot
$taskLocal = Join-Path $taskRoot '.local'
New-Item -ItemType Directory -Path $taskLocal -Force | Out-Null
$taskPg = Join-Path $taskLocal 'pgsql\bin\pg_ctl.exe'
$taskPgData = Join-Path $taskLocal 'pgdata'
if ((Test-Path -LiteralPath $taskPg) -and (Test-Path -LiteralPath $taskPgData)) {
    & $taskPg -D $taskPgData status 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) {
        $taskPort = ((Get-Content -LiteralPath '.env' | Where-Object { $_ -match '^DB_PORT=' }) -replace '^DB_PORT=', '').Trim()
        if ($taskPort -notmatch '^\d+$') { throw 'DB_PORT must be numeric.' }
        & $taskPg -D $taskPgData -l (Join-Path $taskLocal 'postgres.log') -o "-p $taskPort -h 127.0.0.1" -w start
        if ($LASTEXITCODE -ne 0) { throw 'Could not start portable PostgreSQL.' }
    }
}
$taskPhp = (Get-Command php).Source
$taskNode = (Get-Command node).Source
$taskModules = & $taskPhp -m
$taskPhpOptions = @()
if ($taskModules -notcontains 'pdo_pgsql') { $taskPhpOptions = @('-d', 'extension=pdo_pgsql') }
foreach ($taskPort in @(8000,5173)) {
    if (Get-NetTCPConnection -LocalPort $taskPort -State Listen -ErrorAction SilentlyContinue) {
        throw "Port $taskPort is occupied. Use scripts/stop.ps1 if this project is already running."
    }
}
$taskProcesses = @()
try {
$taskProcesses += Start-Process -FilePath $taskPhp -ArgumentList ($taskPhpOptions + @('-S','127.0.0.1:8000','../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')) -WorkingDirectory (Join-Path $taskRoot 'public') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $taskLocal 'api.out.log') -RedirectStandardError (Join-Path $taskLocal 'api.err.log') -PassThru
$taskProcesses += Start-Process -FilePath $taskNode -ArgumentList 'node_modules/vite/bin/vite.js','--host','127.0.0.1' -WorkingDirectory (Join-Path $taskRoot 'frontend') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $taskLocal 'vite.out.log') -RedirectStandardError (Join-Path $taskLocal 'vite.err.log') -PassThru
$taskProcesses += Start-Process -FilePath $taskPhp -ArgumentList ($taskPhpOptions + @('artisan','queue:work','--tries=3','--timeout=60','--sleep=3')) -WorkingDirectory $taskRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $taskLocal 'queue.out.log') -RedirectStandardError (Join-Path $taskLocal 'queue.err.log') -PassThru
Start-Sleep -Seconds 2
foreach ($taskProcess in $taskProcesses) {
    $taskProcess.Refresh()
    if ($taskProcess.HasExited) { throw 'A project process failed to start. Check the logs in .local/.' }
}
$taskState = @($taskProcesses | ForEach-Object { @{ id = $_.Id; startedTicks = $_.StartTime.ToUniversalTime().Ticks.ToString() } })
$taskState | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $taskLocal 'processes.json') -Encoding utf8
} catch {
    foreach ($taskProcess in $taskProcesses) {
        if (-not $taskProcess.HasExited) { $taskProcess.Kill() }
    }
    throw
}
Write-Host 'SmartBusiness: http://localhost:5173'
Write-Host 'API: http://localhost:8000/up'
Write-Host 'Logs: .local/  |  Stop: ./scripts/stop.ps1'
