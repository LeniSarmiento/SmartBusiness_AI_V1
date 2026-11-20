param(
    [switch]$DemoData,
    [switch]$SkipBuild,
    [ValidatePattern('^[a-z0-9][a-z0-9_-]+$')]
    [string]$ProjectName = 'smartbusiness',
    [ValidateRange(1024, 65535)]
    [int]$Port = 8080
)

$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $taskRoot
$taskEnvPath = Join-Path $taskRoot '.env'
$taskEnvExamplePath = Join-Path $taskRoot '.env.example'

function Get-RandomBase64([int]$Bytes) {
    $taskBuffer = New-Object byte[] $Bytes
    $taskGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $taskGenerator.GetBytes($taskBuffer) } finally { $taskGenerator.Dispose() }
    return [Convert]::ToBase64String($taskBuffer)
}

function Get-EnvValue([string]$Key) {
    $taskLine = Get-Content -LiteralPath $taskEnvPath | Where-Object { $_ -match "^$([regex]::Escape($Key))=" } | Select-Object -Last 1
    if (-not $taskLine) { return $null }
    return ($taskLine -replace "^$([regex]::Escape($Key))=", '').Trim().Trim('"')
}

function Set-EnvValue([string]$Key, [string]$Value) {
    $taskLines = [System.Collections.Generic.List[string]](Get-Content -LiteralPath $taskEnvPath)
    $taskFound = $false
    for ($taskIndex = 0; $taskIndex -lt $taskLines.Count; $taskIndex++) {
        if ($taskLines[$taskIndex] -match "^$([regex]::Escape($Key))=") {
            $taskLines[$taskIndex] = "$Key=$Value"
            $taskFound = $true
        }
    }
    if (-not $taskFound) { $taskLines.Add("$Key=$Value") }
    $taskText = ($taskLines -join [Environment]::NewLine) + [Environment]::NewLine
    [IO.File]::WriteAllText($taskEnvPath, $taskText, (New-Object Text.UTF8Encoding($false)))
}

function Invoke-Compose([string[]]$ComposeArguments) {
    & docker compose -p $ProjectName @ComposeArguments
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose falló: $($ComposeArguments -join ' ')"
    }
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker no está instalado o no está disponible en PATH.'
}
& docker info *> $null
if ($LASTEXITCODE -ne 0) { throw 'Docker Desktop no está iniciado.' }

if (-not (Test-Path -LiteralPath $taskEnvPath)) {
    Copy-Item -LiteralPath $taskEnvExamplePath -Destination $taskEnvPath
}

$taskDatabasePassword = Get-EnvValue 'DB_PASSWORD'
if ([string]::IsNullOrWhiteSpace($taskDatabasePassword) -or $taskDatabasePassword -eq 'null') {
    Set-EnvValue 'DB_PASSWORD' ((Get-RandomBase64 24).TrimEnd('=').Replace('+', '-').Replace('/', '_'))
}
$taskAppKey = Get-EnvValue 'APP_KEY'
if ([string]::IsNullOrWhiteSpace($taskAppKey)) {
    Set-EnvValue 'APP_KEY' ('base64:' + (Get-RandomBase64 32))
}

$taskPublicUrl = "http://localhost:$Port"
Set-EnvValue 'APP_ENV' 'local'
Set-EnvValue 'APP_DEBUG' 'false'
Set-EnvValue 'APP_URL' $taskPublicUrl
Set-EnvValue 'FRONTEND_URL' $taskPublicUrl
Set-EnvValue 'CORS_ALLOWED_ORIGINS' $taskPublicUrl
Set-EnvValue 'SANCTUM_STATEFUL_DOMAINS' "localhost:$Port,127.0.0.1:$Port"
Set-EnvValue 'WEB_PORT' ([string] $Port)
Set-EnvValue 'SESSION_SECURE_COOKIE' 'false'

try {
    if (-not $SkipBuild) { Invoke-Compose @('build') }
    Invoke-Compose @('up', '-d', '--wait', '--wait-timeout', '180', 'database')
    Invoke-Compose @('run', '--rm', 'api', 'php', 'artisan', 'migrate', '--seed', '--force')
    if ($DemoData) {
        Invoke-Compose @('run', '--rm', 'api', 'php', 'artisan', 'db:seed', '--class=DemoSeeder', '--force')
    }
    Invoke-Compose @('up', '-d', '--wait', '--wait-timeout', '180')
    & (Join-Path $PSScriptRoot 'docker-smoke.ps1') -ProjectName $ProjectName -Port $Port
    Write-Host "SmartBusiness está listo: $taskPublicUrl"
    if ($DemoData) { Write-Host 'Demo: demo@smartbusiness.test / SmartBusiness2026!' }
    Write-Host "Detener sin borrar datos: docker compose -p $ProjectName down"
    Write-Host "Eliminar también los datos: docker compose -p $ProjectName down -v"
} catch {
    & docker compose -p $ProjectName ps
    & docker compose -p $ProjectName logs --tail 80
    throw
}
