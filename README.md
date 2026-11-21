# SmartBusiness AI · V1

Sistema SaaS multiempresa para gestionar clientes, proveedores, productos, inventario y ventas. API REST con Laravel y PostgreSQL; interfaz React + TypeScript + Vite. Proyecto de portafolio con autorización, integridad de datos y pruebas reales.

> La V1 implementa gestión empresarial y el backend de Tool Calling con permisos. El asistente conectado a un modelo, Laravel AI SDK y RAG pertenecen a la siguiente etapa.

## Vista del producto

![Dashboard](docs/screenshots/dashboard.png)

## Funcionalidades

- Registro de empresa y administrador, login/logout con cookies Sanctum, perfil y recuperación de contraseña.
- Verificación de correo, cambio de contraseña, invitaciones y activación o desactivación segura de miembros.
- Roles administrador, gerente y vendedor, con permisos por empresa.
- Clientes y compras; proveedores y productos asociados.
- Categorías y productos con SKU único por empresa, precios e impuestos configurables.
- Entradas, salidas y ajustes con motivos y referencias; filtros, exportación CSV, alertas persistentes y valorización de inventario.
- Ventas con precios calculados por el servidor, transacciones, bloqueo de filas e idempotencia.
- Anulaciones con movimientos inversos y preservación del historial.
- Dashboard con ventas diarias/mensuales, tendencia, rankings y stock bajo.
- Interfaz adaptable a móvil, búsqueda, paginación y mensajes de error.
- Tests sobre PostgreSQL, incluyendo dos procesos vendiendo la última unidad.
- Hardening con CSP, CORS explícito, rate limits por operación, límite de solicitudes, auditoría sensible y pruebas de ataques multiempresa.
- Tool Calling protegido para ajustar inventario, crear o anular ventas y consultar indicadores; aún no está conectado a un proveedor LLM.

## Tecnologías

Laravel 13, PHP 8.3+ (local y Docker: PHP 8.4), Sanctum 4, PostgreSQL 17, React 19, TypeScript 5, Vite 7, PHPUnit 12, Vitest, Pint, Docker Compose y GitHub Actions.

## Estructura

```text
app/                   API: controllers, requests, resources, models, policies y services
database/              Migraciones, factories y seeders
frontend/              SPA React independiente
tests/                 Unit, Feature y prueba de concurrencia
scripts/               Arranque local para Windows
infra/                 Nginx, PostgreSQL y guía Azure
docs/                  Arquitectura, API, base de datos y mentoría
.github/workflows/     CI y despliegue Azure manual
```

Laravel se mantiene en la raíz porque la carpeta recibida ya contenía esa instalación.

## Arranque en esta máquina (Windows)

La entrega local incluye dependencias, PHP 8.4 portátil en `.local/php` y PostgreSQL 17 portátil en `.local/pgsql` (no versionados). No se modificó la instalación global de XAMPP, `php.ini` del sistema ni servicios Windows. Consulta [la guía de migración local en Windows](docs/windows-php-migration.md).

```powershell
cd C:\Users\lenin\Documents\Laravel\SmartBusiness_AI_V1
.\scripts\dev.ps1
```

Abre **http://localhost:5173**. API: http://localhost:8000/up.

Si PowerShell bloquea scripts, usa la política del proceso, sin cambiar la configuración global:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1
```

Detener: `.\scripts\stop.ps1`. Para detener también PostgreSQL: `.\scripts\stop.ps1 -Database`. Los datos permanecen.

### Acceso de demostración

| Rol | Email |
| --- | --- |
| Administrador | demo@smartbusiness.test |
| Gerente | gerente@smartbusiness.test |
| Vendedor | vendedor@smartbusiness.test |

Contraseña local para estas tres cuentas: **SmartBusiness2026!**. La empresa está identificada como “La Oficina · Demo”; todos sus datos son ficticios. DemoSeeder se niega a ejecutarse en producción.

También puedes crear una empresa vacía desde “Crea tu empresa”.

## Instalación desde un clon limpio

La ruta más corta en Windows requiere únicamente Git y Docker Desktop:

```powershell
git clone https://github.com/LeniSarmiento/SmartBusiness_AI_V1.git
cd SmartBusiness_AI_V1
.\scripts\docker-install.ps1 -DemoData
```

El script crea `.env` si falta, genera `APP_KEY` y una contraseña PostgreSQL locales, construye las imágenes, migra, ejecuta los seeders, espera los health checks y obliga al worker a procesar un Job. No imprime los secretos generados ni elimina volúmenes existentes.

Para una instalación nativa sin Docker:

Requisitos: PHP >= 8.3 (recomendado 8.4) con pdo_pgsql, mbstring, openssl, bcmath, dom, intl, zip y xml; Composer; Node 22.12+ o 24; PostgreSQL 17.

1. `composer install`
2. Copia `.env.example` a `.env`.
3. Crea las bases `smartbusiness` y `smartbusiness_test`; configura un usuario propietario de esas bases.
4. Configura DB_HOST, DB_PORT, DB_USERNAME y DB_PASSWORD.
5. `php artisan key:generate`
6. `php artisan migrate --seed`
7. Opcional, solo local: `php artisan db:seed --class=DemoSeeder`.
8. `npm --prefix frontend ci`.
9. En terminales independientes: `php artisan serve --host=127.0.0.1`, `npm --prefix frontend run dev` y `php artisan queue:work --tries=3`.

El frontend redirige /api y /sanctum hacia el backend durante desarrollo. Usa siempre localhost de forma consistente; no mezcles dominios entre la página y las cookies.

### Variables relevantes

| Variable | Uso |
| --- | --- |
| APP_KEY | Cifrado; generar una vez y conservar de forma segura |
| APP_ENV / APP_DEBUG | local/true para desarrollo; production/false al publicar |
| APP_URL / FRONTEND_URL | URL de API y frontend |
| CORS_ALLOWED_ORIGINS | Orígenes exactos autorizados, separados por coma; nunca `*` con cookies |
| MAX_REQUEST_BYTES | Tamaño máximo aceptado por Laravel; 1048576 por defecto |
| DB_* | Credenciales y conexión PostgreSQL |
| DB_SSLMODE | prefer local; verify-full en cloud con certificados configurados |
| SANCTUM_STATEFUL_DOMAINS | Hosts y puertos de la SPA, sin esquema |
| SESSION_DRIVER | database |
| SESSION_SECURE_COOKIE | true con HTTPS |
| QUEUE_CONNECTION | database; no se necesita Redis en esta V1 |
| MAIL_MAILER | log local; configurar SMTP o proveedor antes de producción |

Localmente, los enlaces de recuperación se escriben en storage/logs/laravel.log. No se envían correos reales hasta configurar un proveedor.

## Testing

```powershell
.\scripts\php.ps1 vendor/bin/phpunit
.\scripts\php.ps1 vendor/bin/phpunit --testsuite Unit
.\scripts\php.ps1 vendor/bin/phpunit --testsuite Integration
.\scripts\php.ps1 vendor/bin/phpunit --testsuite Functional
.\scripts\php.ps1 vendor/bin/pint --test
npm --prefix frontend test
npm --prefix frontend run build
```

En PHP con pdo_pgsql ya habilitado: `php artisan test`. En XAMPP se usa PHPUnit directamente porque el subproceso de artisan test no hereda la opción -d de PHP. PHPUnit fija **smartbusiness_test**, nunca la base de desarrollo. Las suites se ejecutan secuencialmente porque las pruebas con migraciones reconstruyen esa base. La prueba de concurrencia levanta dos procesos PHP y verifica que solo uno puede vender la última unidad.

Cobertura: unidades monetarias aisladas; integración de servicios con PostgreSQL; flujo funcional completo desde el registro de una empresa hasta una venta y su anulación; autenticación, CSRF, rate limit, permisos, aislamiento, claves foráneas, stock negativo, rollback, idempotencia, catálogo, zonas horarias y concurrencia. Consulta [la estrategia de pruebas](docs/testing.md). No equivale a una auditoría de seguridad ni a una prueba de carga.

## Docker

Instalación completa con datos demostrativos:

```powershell
.\scripts\docker-install.ps1 -DemoData
```

Sin datos demo: `.\scripts\docker-install.ps1`. Para otro puerto o un stack aislado: `.\scripts\docker-install.ps1 -ProjectName smartbusiness-prueba -Port 18080 -DemoData`.

Operación manual equivalente:

1. Copia `.env.example` a `.env`, genera `APP_KEY` y define `DB_PASSWORD`.
2. `docker compose build`.
3. `docker compose up -d --wait database`.
4. `docker compose run --rm api php artisan migrate --seed --force`.
5. Opcional local: `docker compose run --rm api php artisan db:seed --class=DemoSeeder --force`.
6. `docker compose up -d --wait`.
7. `docker compose exec -T api php artisan app:queue-smoke --timeout=45`.
8. Abre **http://localhost:8080**.

Servicios: Nginx sirve React y dirige la API a PHP-FPM; api ejecuta Laravel; worker procesa el listener de ventas; database persiste PostgreSQL en un volumen. La base no expone un puerto público. La imagen final excluye dependencias de desarrollo y secretos.

Los cuatro servicios tienen health checks. `web` comprueba React y `/up`; `api` inicia Laravel y consulta PostgreSQL; `database` usa `pg_isready`; `worker` comprueba Laravel, PostgreSQL y las tablas de cola. El comando `app:queue-smoke` demuestra además que el worker procesa un Job real.

Prueba reproducible de los cuatro servicios, HTTP, un Job real y conservación de datos tras recrear los contenedores:

```powershell
.\scripts\docker-smoke.ps1 -Recreate
```

Si usaste un nombre o puerto personalizados, repítelos: `.\scripts\docker-smoke.ps1 -ProjectName smartbusiness-prueba -Port 18080 -Recreate`.

Detener y conservar información: `docker compose down`. Volver a levantar: `docker compose up -d --wait`. El volumen `postgres-data` conserva los datos incluso cuando se recrea el contenedor. Solo `docker compose down -v` elimina el volumen y debe utilizarse de forma intencional.

## CI/CD y Git

CI valida Composer, formato PHP, tests PostgreSQL en PHP 8.3/8.4, tests/build React y auditorías de dependencias. Después construye el stack Docker completo, migra, carga datos demo, comprueba los cuatro health checks y un Job real, recrea los contenedores y confirma que PostgreSQL conservó los datos. El workflow Azure es manual y requiere recursos previamente configurados. No se ha ejecutado en GitHub ni Azure desde esta entrega.

main contiene la instalación inicial; feat/smartbusiness-v1 contiene commits separados por área. No hay remoto ni Pull Request creados. Al publicar, configura tu remoto y abre un PR desde esta branch. No subas .env, .local/, vendor/ ni node_modules/.

## Documentación

- [Arquitectura y decisiones](docs/architecture.md)
- [Modelo de datos](docs/database.md)
- [Endpoints y ejemplos](docs/api.md)
- [Estrategia de pruebas](docs/testing.md)
- [Seguridad operativa y checklist](docs/security.md)
- [Migración local en Windows a PHP 8.3+ y Laravel 13](docs/windows-php-migration.md)
- [Backlog pendiente para Antigravity](docs/antigravity-backlog.md)
- [Guía Azure y despliegue](infra/azure/README.md)
- [Roadmap y primera tarea de mentoría](docs/roadmap.md)

## Estado y límites

La aplicación funciona localmente y está publicada en GitHub. Correo real, dominio, TLS y recursos Azure requieren configuración externa. No incluye pagos, factura fiscal, compras a proveedores, almacenes múltiples, roles personalizados, asistente LLM ni RAG. Cantidades enteras y una moneda base por empresa.

Antes de un uso empresarial real: revisa reglas fiscales y de redondeo del país, configura copias de seguridad con restauración probada, monitoreo, correo, acceso de red y versiones soportadas de PHP/Laravel. Las tasas del catálogo demo son ilustrativas.
