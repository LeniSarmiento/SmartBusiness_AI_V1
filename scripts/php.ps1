param([Parameter(ValueFromRemainingArguments=$true)][string[]]$PhpArguments)
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskPhp = (Get-Command php).Source
$taskOptions = @()
if ((& $taskPhp -m) -notcontains 'pdo_pgsql') { $taskOptions = @('-d','extension=pdo_pgsql') }
Push-Location -LiteralPath $taskRoot
try {
    & $taskPhp @taskOptions @PhpArguments
    $taskExitCode = $LASTEXITCODE
} finally { Pop-Location }
exit $taskExitCode
