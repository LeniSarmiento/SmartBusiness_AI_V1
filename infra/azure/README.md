# Azure: preparación y despliegue

El proyecto incluye un workflow manual; **no se ha desplegado ni probado en Azure**. No se han creado recursos ni cargos. La primera configuración requiere una suscripción y decisiones sobre región, red, presupuesto y dominio.

## Topología prevista

- Azure Container Registry para dos imágenes: API PHP-FPM y web Nginx/React.
- Una Azure Container App con contenedores llamados api, web y worker. Comparten red. web escucha en 8080 y usa API_UPSTREAM=127.0.0.1:9000.
- api y worker utilizan la misma imagen. worker ejecuta php artisan queue:work --tries=3 --timeout=60. Mantener al menos una réplica si se necesita procesamiento continuo.
- PostgreSQL Flexible Server accesible por red privada desde el entorno de Container Apps.
- Un Container Apps Job manual, de un solo contenedor, para php artisan migrate --force.
- Logs a stderr y Log Analytics; configurar alertas para HTTP 5xx, jobs fallidos y conexión DB.

La imagen api usa www-data, y no contiene secretos. Nginx es el único punto de entrada público. Solo se expone HTTPS; el ingreso hacia PHP-FPM queda interno.

## Configuración previa

1. Crear los recursos indicados y una base vacía con un usuario de aplicación de privilegios limitados.
2. Crear los secretos app-key y db-password en Container Apps/Key Vault. Usar secretRef en los contenedores, nunca valores dentro del repositorio.
3. Configurar APP_ENV=production, APP_DEBUG=false, APP_URL y FRONTEND_URL con la URL HTTPS pública, SANCTUM_STATEFUL_DOMAINS con el host, SESSION_SECURE_COOKIE=true, SESSION_DRIVER=database, QUEUE_CONNECTION=database, LOG_CHANNEL=stderr y DB_SSLMODE=verify-full. Configurar el certificado raíz de PostgreSQL para la verificación TLS.
4. Dar a la identidad administrada de la app y del job permiso de lectura en ACR. Configurar registry identity, sin habilitar la cuenta administradora de ACR.
5. Crear un job con el comando exacto php artisan migrate --force, las mismas variables/secretos DB y la imagen api. No usar migrate:fresh en cloud. Ejecutar RolePermissionSeeder una vez mediante un job controlado antes de abrir el registro.
6. Provisionar probes de readiness y liveness HTTP sobre /up, puerto 8080, y revisar la conexión DB por separado.
7. Configurar el proveedor de correo y verificar recuperación de contraseña.
8. Configurar OIDC entre GitHub y Azure. Dar acceso de publicación en ACR y despliegue únicamente a estos recursos. Crear el environment production de GitHub con revisores si corresponde.

## Permisos mínimos

- La identidad federada de GitHub no debe ser `Owner`, `Contributor` de la suscripción ni `User Access Administrator`. Limitarla al grupo de recursos de SmartBusiness y, cuando sea viable, usar un rol personalizado con lectura de la Container App/Job y permiso para actualizar imágenes e iniciar/consultar el job.
- Para ACR, asignar `AcrPush` a la identidad de despliegue únicamente sobre ese registro. La identidad administrada de Container Apps necesita solo `AcrPull`.
- La identidad de ejecución puede leer únicamente los secretos concretos de Key Vault requeridos por la aplicación. No debe crear, borrar ni listar secretos de otros proyectos.
- Mantener PostgreSQL en red privada, sin acceso público general. Permitir únicamente el entorno de Container Apps y conexiones administrativas controladas.
- Separar `smartbusiness_migrator` y `smartbusiness_app`: el job de migración usa el primero; api y worker usan el segundo sin `CREATE`, `ALTER`, `DROP`, superusuario, creación de roles o bases. La plantilla está en `infra/postgres/least-privilege-roles.sql.example`.
- Un rol opcional `smartbusiness_readonly` puede usarse para soporte/reportes. No reutilizar la credencial de la aplicación en herramientas humanas.
- Revisar asignaciones RBAC, accesos de Key Vault, reglas de red y usuarios PostgreSQL trimestralmente y después de cada incidente o cambio de personal.

Variables GitHub del environment production:

AZURE_CLIENT_ID, AZURE_TENANT_ID, AZURE_SUBSCRIPTION_ID, AZURE_RESOURCE_GROUP, AZURE_ACR_NAME, AZURE_CONTAINER_APP y AZURE_MIGRATION_JOB.

## Pipeline manual

Desde main, ejecutar Deploy Azure (manual). Primero repite CI, luego construye/publica imágenes etiquetadas con el commit SHA, ejecuta el job de migración y espera el éxito. Finalmente conserva la plantilla existente y cambia api/web/worker en una sola revisión. El script no aprovisiona infraestructura ni modifica secretos.

Usar migraciones compatibles con la versión anterior (expand/contract), ya que el código anterior sigue atendiendo mientras se migra. Si la migración falla, la app no se actualiza. El script no revierte una migración parcialmente aplicada: revisar los logs y corregirla antes del siguiente intento.

Tras desplegar, comprobar readiness, login, registro, permisos, una venta/anulación y logs del worker. Conservar una revisión anterior para rollback de código; restaurar DB requiere un procedimiento y copias previamente probados.

Comprobar además CSP, cookies Secure/HttpOnly/SameSite, CORS desde un origen no autorizado, respuesta 413 para cuerpos mayores a 1 MiB, rate limits y consulta de auditoría. Usa `docs/security.md` como checklist.

## Fuentes oficiales

- [Azure CLI Container Apps](https://learn.microsoft.com/en-us/cli/azure/containerapp)
- [Jobs y ejecuciones](https://learn.microsoft.com/en-us/cli/azure/containerapp/job/execution)
- [ACR con identidad administrada](https://learn.microsoft.com/en-us/azure/container-apps/managed-identity-image-pull)
- [OIDC desde GitHub](https://docs.github.com/en/actions/how-tos/secure-your-work/security-harden-deployments/oidc-in-azure)

No se declara este pipeline validado en cloud hasta ejecutarlo contra los recursos reales.
