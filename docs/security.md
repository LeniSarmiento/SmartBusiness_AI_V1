# Seguridad operativa

## Controles implementados

- Autenticación SPA con Sanctum, cookies cifradas, HttpOnly y SameSite; CSRF obligatorio fuera de testing.
- Revocación de sesiones secundarias, tokens Sanctum y remember tokens al cambiar o restablecer contraseña.
- Revocación completa de sesiones y tokens al desactivar o retirar un miembro.
- Límites separados para login, registro, recuperación, verificación, invitaciones, operaciones sensibles, escrituras y Tool Calling.
- CORS con orígenes explícitos; nunca se permite `*` junto con credenciales.
- CSP y headers defensivos en Laravel y Nginx; HSTS en producción HTTPS.
- Límite de 1 MiB por solicitud en Laravel y Nginx, con límites coherentes en PHP.
- Respuestas 5xx genéricas en producción con `X-Request-ID`; el detalle queda en logs.
- Auditoría multiempresa de roles, altas/bajas/desactivaciones, ajustes manuales de stock y anulaciones.
- Verificación de email mediante URL firmada y Tool Calling de ventas con idempotencia obligatoria.

## Auditoría y respuesta

Los administradores pueden consultar `GET /api/v1/organizations/{organization}/security-audit-logs`. Los registros contienen actor, evento, sujeto, antes/después, IP, agente de usuario y fecha. No deben incluir contraseñas, tokens, cookies ni secretos.

Configura una alerta sobre:

- crecimiento anormal de respuestas 401, 403, 419, 429 y 5xx;
- múltiples intentos de login, aceptación de invitaciones o reset desde una misma IP;
- cambios de administrador, desactivaciones, anulaciones y ajustes de inventario fuera de horario;
- fallos de jobs, auditorías de dependencias o escaneos del pipeline.

Conserva los logs según la política del negocio y restringe su lectura. Un registro de auditoría no sustituye backups, alertas ni un SIEM.

## Checklist de producción

1. `APP_ENV=production`, `APP_DEBUG=false`, HTTPS y `SESSION_SECURE_COOKIE=true`.
2. Usar un único origen público para SPA y API cuando sea posible. Definir `CORS_ALLOWED_ORIGINS` sin comodines.
3. Guardar APP_KEY, credenciales DB y correo en Key Vault; rotarlas y probar la recuperación.
4. Ejecutar migraciones con un rol PostgreSQL separado. La aplicación no debe tener permisos DDL.
5. Validar restauración de backups, point-in-time restore y alertas antes de recibir datos reales.
6. Ejecutar CI, workflow Security, pruebas multiempresa y smoke test de permisos antes de desplegar.

Consulta `infra/postgres/least-privilege-roles.sql.example` y `infra/azure/README.md` para permisos cloud.

