param([Parameter(ValueFromRemainingArguments=$true)][string[]]$PhpArguments)
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskLocalPhp = Join-Path $taskRoot '.local\php\php.exe'
if (-not [string]::IsNullOrWhiteSpace($env:SMARTBUSINESS_PHP)) {
    if (-not (Test-Path -LiteralPath $env:SMARTBUSINESS_PHP)) {
        throw "SMARTBUSINESS_PHP apunta a una ruta inexistente: $env:SMARTBUSINESS_PHP"
    }
    $taskPhp = $env:SMARTBUSINESS_PHP
} elseif (Test-Path -LiteralPath $taskLocalPhp) {
    $taskPhp = $taskLocalPhp
} elseif (Get-Command php -ErrorAction SilentlyContinue) {
    $taskPhp = (Get-Command php).Source
} else {
    throw 'No se encontró PHP. Consulta docs/windows-php-migration.md para configurar PHP 8.3+.'
}
& $taskPhp -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);"
if ($LASTEXITCODE -ne 0) {
    $taskVersion = & $taskPhp -r "echo PHP_VERSION;"
    throw "Se requiere PHP 8.3+ para Laravel 13 (detectado $taskVersion en $taskPhp). Consulta docs/windows-php-migration.md."
}
$taskOptions = @()
if ((& $taskPhp -m) -notcontains 'pdo_pgsql') { $taskOptions = @('-d','extension=pdo_pgsql') }
Push-Location -LiteralPath $taskRoot
try {
    & $taskPhp @taskOptions @PhpArguments
    $taskExitCode = $LASTEXITCODE
} finally { Pop-Location }
exit $taskExitCode
