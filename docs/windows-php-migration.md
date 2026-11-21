# Guía de migración local en Windows a PHP 8.3+ y Laravel 13

SmartBusiness requiere oficialmente **PHP 8.3 o superior** (recomendado **PHP 8.4**) y **Laravel 13**.

Para proteger otros proyectos locales que aún dependan de XAMPP con PHP 8.2, el repositorio **no modifica la instalación global de Windows, ni `C:\xampp`, ni el `PATH` del sistema**.

## Orden de detección de PHP en los scripts del proyecto

Los scripts [`scripts/php.ps1`](../scripts/php.ps1) y [`scripts/dev.ps1`](../scripts/dev.ps1) resuelven el binario de PHP en este orden:

1. **`$env:SMARTBUSINESS_PHP`**: ruta explícita configurada en la sesión actual de PowerShell.
2. **`.local\php\php.exe`**: instalación portátil aislada dentro del proyecto (ignorada por Git).
3. **`php` en el `PATH` global**: solo si su versión es `>= 8.3.0`. Si detecta PHP 8.2, detiene la ejecución con un mensaje claro.

---

## Opción 1 (Recomendada): PHP 8.4 portátil en `.local\php`

Esta opción mantiene intacto tu XAMPP global y aísla PHP 8.4 dentro de `.local\php\` (junto al PostgreSQL portátil de `.local\pgsql\`).

1. Descarga el paquete oficial Thread Safe x64 (`vs17-x64`) de PHP 8.4 desde [windows.php.net/download](https://windows.php.net/download/).
2. Verifica el hash SHA-256 publicado por PHP y extrae el contenido en `.local\php\`:

```powershell
cd C:\Users\lenin\Documents\Laravel\SmartBusiness_AI_V1
$releases = Invoke-RestMethod -Uri "https://windows.php.net/downloads/releases/releases.json"
$pkg = $releases.'8.4'.'ts-vs17-x64'.zip
$zipPath = Join-Path ".local" $pkg.path

Invoke-WebRequest -Uri "https://windows.php.net/downloads/releases/$($pkg.path)" -OutFile $zipPath -UseBasicParsing
$hash = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
if ($hash -ne $pkg.sha256.ToLowerInvariant()) {
    Remove-Item $zipPath -Force
    throw "Checksum SHA-256 inválido."
}

New-Item -ItemType Directory -Path ".local\php" -Force | Out-Null
Expand-Archive -Path $zipPath -DestinationPath ".local\php" -Force
Remove-Item $zipPath -Force
```

3. Crea `.local\php\php.ini` a partir de `php.ini-development` y habilita las extensiones requeridas por Laravel 13 y PostgreSQL:

```powershell
$iniPath = ".local\php\php.ini"
Copy-Item ".local\php\php.ini-development" $iniPath -Force
$ini = Get-Content $iniPath -Raw
$ini = $ini -replace ';extension_dir = "ext"', 'extension_dir = "ext"'
foreach ($ext in @('curl', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_pgsql', 'pgsql', 'zip')) {
    $ini = $ini -replace ";extension=$ext", "extension=$ext"
}
[System.IO.File]::WriteAllText((Resolve-Path $iniPath), $ini, (New-Object System.Text.UTF8Encoding($false)))
```

---

## Opción 2: Apuntar a otra instalación de PHP 8.3/8.4 mediante `SMARTBUSINESS_PHP`

Si ya instalaste PHP 8.3 o 8.4 en otra carpeta (por ejemplo `C:\tools\php84\php.exe` con Scoop, Winget o descarga manual) y no deseas cambiar el `PATH` global de Windows:

```powershell
$env:SMARTBUSINESS_PHP = "C:\tools\php84\php.exe"
.\scripts\php.ps1 -v
```

---

## Opción 3: Uso exclusivo mediante Docker Desktop

Si prefieres no gestionar binarios de PHP en Windows, el stack Docker ya incluye `php:8.4-fpm-bookworm` con todas las extensiones compiladas:

```powershell
.\scripts\docker-install.ps1 -DemoData
.\scripts\docker-smoke.ps1 -Recreate
```

---

## Comprobación posterior a la migración

Ejecuta estos comandos desde la raíz del repositorio para confirmar que PHP 8.3+, Composer y Laravel 13 funcionan correctamente sobre la base existente y la base de pruebas:

```powershell
.\scripts\php.ps1 -v
.\scripts\php.ps1 C:\composer\composer.phar validate --strict
.\scripts\php.ps1 artisan --version
.\scripts\php.ps1 artisan migrate --force
.\scripts\php.ps1 vendor/bin/pint --test
.\scripts\php.ps1 vendor/bin/phpunit
```
