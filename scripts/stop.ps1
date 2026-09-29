param([switch]$Database)
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskStatePath = Join-Path $taskRoot '.local\processes.json'
if (Test-Path -LiteralPath $taskStatePath) {
    $taskState = Get-Content -Raw -LiteralPath $taskStatePath | ConvertFrom-Json
    foreach ($taskSaved in $taskState) {
        $taskProcess = Get-Process -Id $taskSaved.id -ErrorAction SilentlyContinue
        if ($taskProcess -and $taskProcess.StartTime.ToUniversalTime().Ticks.ToString() -eq [string]$taskSaved.startedTicks) {
            Stop-Process -Id $taskSaved.id
        }
    }
}
if ($Database) {
    $taskPg = Join-Path $taskRoot '.local\pgsql\bin\pg_ctl.exe'
    if (Test-Path -LiteralPath $taskPg) { & $taskPg -D (Join-Path $taskRoot '.local\pgdata') -m fast -w stop }
}
Write-Host 'Project processes stopped. Database files are preserved.'
