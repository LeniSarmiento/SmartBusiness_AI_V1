# Observabilidad y rendimiento

SmartBusiness emite señales operativas sin registrar cuerpos de solicitudes, contraseñas, tokens, cookies, bindings SQL ni payloads de Jobs.

## Correlación HTTP

Cada respuesta incluye `X-Request-ID`. El cliente puede enviar un identificador formado por 8 a 100 caracteres alfanuméricos, punto, guion o guion bajo; cualquier valor inválido se reemplaza por un UUID.

El evento `http.request.completed` contiene método, ruta sin query string, estado, duración, usuario y organización cuando están disponibles. `/up` se excluye por defecto para evitar ruido.

## PostgreSQL

`database.slow_query` se registra cuando una consulta supera `DB_SLOW_QUERY_MS`. Solo se guarda la conexión, duración, operación, tipo de lectura/escritura y un hash SHA-256 de la sentencia normalizada. SQL y bindings no se escriben en logs.

## Jobs y Queues

- `queue.job.processed`: Job completado y duración.
- `queue.job.exception`: intento que lanzó una excepción y puede reintentarse.
- `queue.job.failed`: Job agotado o marcado como fallido.

Los eventos incluyen nombre del Job, cola, conexión, intentos y clase de excepción, pero no el mensaje ni el payload.

## Diagnóstico seguro

```powershell
.\scripts\php.ps1 artisan app:diagnostics
.\scripts\php.ps1 artisan app:diagnostics --json
.\scripts\php.ps1 artisan app:diagnostics --fail-on-failed-jobs
```

El comando comprueba aplicación, PostgreSQL, migraciones, cache y tablas de cola. No imprime credenciales, URLs de conexión ni variables de entorno.

## Variables

| Variable | Valor inicial | Uso |
| --- | --- | --- |
| `OBSERVABILITY_HTTP_LOGGING` | `true` | Registra resúmenes HTTP estructurados |
| `OBSERVABILITY_HTTP_EXCLUDED_PATHS` | `up` | Patrones Laravel separados por coma que no generan evento HTTP |
| `DB_SLOW_QUERY_LOGGING` | `true` | Activa detección de consultas lentas |
| `DB_SLOW_QUERY_MS` | `250` | Umbral en milisegundos |
| `QUEUE_LOG_PROCESSED_JOBS` | `true` | Registra Jobs completados |
| `LOG_STDERR_FORMATTER` | `Monolog\Formatter\JsonFormatter` | JSON de una línea en contenedores |

## Prueba de carga

La prueba k6 autentica una sola sesión y ejecuta lecturas de dashboard, productos, movimientos y ventas. No crea ni modifica datos.

```powershell
.\scripts\load-test.ps1 `
  -BaseUrl http://localhost:8080 `
  -Email demo@smartbusiness.test `
  -Password '<contraseña-local>' `
  -VirtualUsers 3 `
  -Duration 30s
```

El script usa k6 instalado o la imagen oficial `grafana/k6:2.3.0`. La contraseña se recibe como parámetro y nunca se guarda en el repositorio.

Umbrales iniciales:

- menos de 1 % de solicitudes fallidas;
- más de 99 % de checks correctos;
- p95 global menor a 1500 ms;
- p95 del dashboard menor a 2000 ms;
- p95 de los listados menor a 1000 ms.

Estos valores son una línea base de regresión, no una promesa de capacidad. Para aumentar usuarios o duración se debe usar un ambiente de staging aislado, nunca producción sin autorización.

## Investigación de incidentes

1. Obtener el `X-Request-ID` de la respuesta reportada.
2. Buscar `request_id` en los logs JSON de API y worker.
3. Revisar estado y duración HTTP, consultas lentas y eventos de cola relacionados.
4. Ejecutar `app:diagnostics --fail-on-failed-jobs` dentro del contenedor API.
5. Conservar evidencia antes de reintentar o eliminar Jobs fallidos.
