param(
    [ValidatePattern('^[a-z0-9][a-z0-9_-]+$')]
    [string]$ProjectName = 'smartbusiness',
    [ValidateRange(1024, 65535)]
    [int]$Port = 8080,
    [switch]$Recreate
)

$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $taskRoot
$taskPublicUrl = "http://localhost:$Port"

function Invoke-Compose([string[]]$ComposeArguments) {
    & docker compose -p $ProjectName @ComposeArguments
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose falló: $($ComposeArguments -join ' ')"
    }
}

function Assert-ServicesHealthy {
    foreach ($taskService in @('database', 'api', 'worker', 'web')) {
        $taskContainerId = (& docker compose -p $ProjectName ps -q $taskService).Trim()
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($taskContainerId)) {
            throw "El servicio $taskService no está iniciado."
        }

        $taskHealth = (& docker inspect --format '{{.State.Health.Status}}' $taskContainerId).Trim()
        if ($LASTEXITCODE -ne 0 -or $taskHealth -ne 'healthy') {
            throw "El servicio $taskService no está saludable: $taskHealth"
        }
    }
}

function Assert-HttpEndpoints {
    $taskWebResponse = Invoke-WebRequest -UseBasicParsing -Uri "$taskPublicUrl/" -TimeoutSec 15
    if ($taskWebResponse.StatusCode -ne 200 -or $taskWebResponse.Content -notmatch '<div id="root"></div>') {
        throw 'React no respondió correctamente.'
    }

    $taskApiResponse = Invoke-WebRequest -UseBasicParsing -Uri "$taskPublicUrl/up" -TimeoutSec 15
    if ($taskApiResponse.StatusCode -ne 200) {
        throw 'Laravel /up no respondió con HTTP 200.'
    }
}

function Get-DatabaseFingerprint {
    $taskSql = "SELECT concat((SELECT count(*) FROM migrations), ':', (SELECT count(*) FROM organizations), ':', (SELECT count(*) FROM users), ':', (SELECT count(*) FROM sales));"
    $taskResult = (& docker compose -p $ProjectName exec -T database psql -v ON_ERROR_STOP=1 -U smartbusiness -d smartbusiness -tA -c $taskSql).Trim()
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($taskResult)) {
        throw 'No se pudo obtener la huella de datos de PostgreSQL.'
    }

    return $taskResult
}

function Invoke-SmokeChecks {
    Assert-ServicesHealthy
    Assert-HttpEndpoints
    Invoke-Compose @('exec', '-T', 'api', 'php', 'artisan', 'app:health-check')
    Invoke-Compose @('exec', '-T', 'worker', 'php', 'artisan', 'app:health-check', '--queue')
    Invoke-Compose @('exec', '-T', 'api', 'php', 'artisan', 'app:diagnostics', '--fail-on-failed-jobs')
    Invoke-Compose @('exec', '-T', 'api', 'php', 'artisan', 'app:queue-smoke', '--timeout=45')
}

try {
    Invoke-SmokeChecks
    $taskFingerprintBefore = Get-DatabaseFingerprint

    if ($Recreate) {
        Invoke-Compose @('down')
        Invoke-Compose @('up', '-d', '--wait', '--wait-timeout', '180')
        Invoke-SmokeChecks
        $taskFingerprintAfter = Get-DatabaseFingerprint

        if ($taskFingerprintAfter -ne $taskFingerprintBefore) {
            throw "Los datos cambiaron al recrear los contenedores: $taskFingerprintBefore -> $taskFingerprintAfter"
        }

        Write-Host "Persistencia verificada: $taskFingerprintAfter (migraciones:organizaciones:usuarios:ventas)."
    }

    Invoke-Compose @('ps')
    Write-Host "Smoke test Docker correcto: $taskPublicUrl"
} catch {
    & docker compose -p $ProjectName ps
    & docker compose -p $ProjectName logs --tail 80
    throw
}
