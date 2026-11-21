# API REST V1

Base: /api/v1. Respuestas JSON; recursos en data; listados con links y meta. Paginación por defecto 20, máximo 100. Errores de validación: 422 con message y errors. No autenticado: 401; sin permiso: 403; recurso ajeno/inexistente: 404; conflicto de negocio: 409; límite de solicitudes: 429.

## Sesión

1. GET /sanctum/csrf-cookie.
2. POST /api/v1/auth/login con email y password.
3. Enviar cookies de sesión, Accept: application/json, Origin o Referer del frontend y X-XSRF-TOKEN en operaciones de escritura.
4. GET /api/v1/auth/me para obtener empresas, rol y permisos.
5. POST /api/v1/auth/logout.

No usar Bearer tokens para la SPA. Las rutas register/login requieren un origen configurado en SANCTUM_STATEFUL_DOMAINS.

| Método | Ruta | Propósito |
| --- | --- | --- |
| POST | /auth/register | Empresa + administrador + sesión |
| POST | /auth/forgot-password | Enlace por correo (respuesta genérica) |
| POST | /auth/reset-password | Token, email, password, password_confirmation |
| PATCH | /auth/profile | Nombre, correo y current_password |
| POST | /auth/change-password | Cambio de contraseña y cierre de otras sesiones |
| GET | /auth/email/verify/{id}/{hash} | Verificación de correo con URL firmada |
| POST | /auth/email/resend | Reenviar enlace de verificación |
| GET | /invitations/{token} | Inspeccionar invitación pendiente |
| POST | /invitations/{token}/accept | Aceptar invitación a una organización |
| GET/POST | /organizations/{org}/customers | Buscar/listar o crear clientes |
| GET/PATCH/DELETE | /organizations/{org}/customers/{id} | Consultar/editar/archivar |
| GET | /organizations/{org}/customers/{id}/sales | Historial paginado |
| GET/POST | /organizations/{org}/suppliers | Proveedores |
| GET/PATCH/DELETE | /organizations/{org}/suppliers/{id} | Proveedor |
| GET | /organizations/{org}/suppliers/{id}/products | Productos relacionados |
| GET/POST | /organizations/{org}/categories | Categorías |
| GET/PATCH/DELETE | /organizations/{org}/categories/{id} | Categoría |
| GET/POST | /organizations/{org}/products | Catálogo |
| GET/PATCH/DELETE | /organizations/{org}/products/{id} | Producto |
| GET/POST | /organizations/{org}/inventory-movements | Historial o movimiento manual |
| GET | /organizations/{org}/inventory-movements/export | Exportar historial filtrado en CSV |
| GET/POST | /organizations/{org}/adjustment-reasons | Listar o crear motivos de ajuste |
| PUT/PATCH/DELETE | /organizations/{org}/adjustment-reasons/{id} | Editar o eliminar motivo de ajuste |
| GET | /organizations/{org}/stock-alerts | Alertas de stock bajo |
| GET | /organizations/{org}/reports/inventory-valuation | Reporte de valorización de inventario |
| GET/POST | /organizations/{org}/sales | Listar o completar una venta |
| GET | /organizations/{org}/sales/{id} | Venta y líneas |
| POST | /organizations/{org}/sales/{id}/cancel | Anular con reason |
| GET | /organizations/{org}/dashboard | Indicadores reales de la empresa |
| GET/POST | /organizations/{org}/members | Equipo o crear usuario |
| PATCH/DELETE | /organizations/{org}/members/{id} | Cambiar rol, activar/desactivar o retirar miembro |
| GET/POST | /organizations/{org}/invitations | Listar o enviar invitaciones por enlace |
| DELETE | /organizations/{org}/invitations/{id} | Revocar invitación pendiente |
| GET | /organizations/{org}/security-audit-logs | Bitácora de auditoría de seguridad |
| GET | /organizations/{org}/ai/tools | Esquemas de herramientas IA autorizadas por rol |
| POST | /organizations/{org}/ai/tools/execute | Ejecutar herramienta IA autorizada |

Catálogo: search, page, per_page, active=1/0. Productos: low_stock=1. Ventas: status=completed/cancelled, from=YYYY-MM-DD, to=YYYY-MM-DD, search por cliente. Fechas interpretadas en la zona de la organización. Movimientos: product_id, user_id, type, from, to, search.

## Crear una venta

```json
{
  "request_key": "61bba73c-e91b-487c-a97b-cf900e67e884",
  "customer_id": 1,
  "notes": "Entrega en oficina",
  "items": [{"product_id": 1, "quantity": 2}]
}
```

customer_id puede ser null (consumidor final). No se aceptan importes, vendedor ni organización en el cuerpo. Máximo 100 productos distintos y 10.000 unidades por línea. Los precios se envían como cadenas decimales en el catálogo y respuestas. tax_rate_bps=1900 significa 19%; es una configuración del producto, no una regla fiscal universal.

## Movimiento manual

```json
{"product_id":1,"type":"adjustment","direction":"decrease","quantity":2,"reason":"Unidades dañadas en transporte"}
```

type: entry, exit o adjustment. direction se requiere para adjustment. La cantidad es positiva; el servicio determina el signo.
