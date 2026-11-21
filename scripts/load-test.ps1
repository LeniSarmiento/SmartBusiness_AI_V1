param(
    [string]$BaseUrl = 'http://localhost:8080',
    [Parameter(Mandatory)]
    [string]$Email,
    [Parameter(Mandatory)]
    [string]$Password,
    [ValidateRange(1, 100)]
    [int]$VirtualUsers = 3,
    [ValidatePattern('^[1-9][0-9]*(s|m)$')]
    [string]$Duration = '30s'
)

$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskScriptPath = Join-Path $taskRoot 'tests\Performance\k6\smoke.js'

if (Get-Command k6 -ErrorAction SilentlyContinue) {
    $taskPreviousEnvironment = @{
        BASE_URL = $env:BASE_URL
        APP_ORIGIN = $env:APP_ORIGIN
        LOAD_TEST_EMAIL = $env:LOAD_TEST_EMAIL
        LOAD_TEST_PASSWORD = $env:LOAD_TEST_PASSWORD
        K6_VUS = $env:K6_VUS
        K6_DURATION = $env:K6_DURATION
    }

    try {
        $env:BASE_URL = $BaseUrl
        $env:APP_ORIGIN = $BaseUrl
        $env:LOAD_TEST_EMAIL = $Email
        $env:LOAD_TEST_PASSWORD = $Password
        $env:K6_VUS = [string] $VirtualUsers
        $env:K6_DURATION = $Duration
        & k6 run $taskScriptPath
        if ($LASTEXITCODE -ne 0) { throw 'La prueba k6 no cumplió los umbrales.' }
    } finally {
        foreach ($taskVariable in $taskPreviousEnvironment.GetEnumerator()) {
            Set-Item -Path "Env:$($taskVariable.Key)" -Value $taskVariable.Value
        }
    }

    exit 0
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Instala k6 o inicia Docker Desktop para ejecutar la prueba de carga.'
}

$taskContainerBaseUrl = $BaseUrl.Replace('localhost', 'host.docker.internal').Replace('127.0.0.1', 'host.docker.internal')
$taskMount = (Split-Path -Parent $taskScriptPath) + ':/scripts:ro'

& docker run --rm `
    --add-host 'host.docker.internal:host-gateway' `
    --volume $taskMount `
    --env "BASE_URL=$taskContainerBaseUrl" `
    --env "APP_ORIGIN=$BaseUrl" `
    --env "LOAD_TEST_EMAIL=$Email" `
    --env "LOAD_TEST_PASSWORD=$Password" `
    --env "K6_VUS=$VirtualUsers" `
    --env "K6_DURATION=$Duration" `
    grafana/k6:2.3.0 run /scripts/smoke.js

if ($LASTEXITCODE -ne 0) {
    throw 'La prueba k6 no cumplió los umbrales.'
}
