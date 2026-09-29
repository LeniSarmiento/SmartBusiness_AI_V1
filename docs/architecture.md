# Arquitectura V1

SmartBusiness es un monolito Laravel con una SPA React separada. Laravel ya existía en la raíz del proyecto; se conservó esa disposición. PostgreSQL es la única base soportada por estas migraciones.

## Flujo de una petición

Ruta /api/v1 → sesión Sanctum → membresía de empresa → Form Request → Controller → Policy → Service (si hay reglas de negocio) → Eloquent → API Resource.

La SPA usa cookies HTTP-only de sesión y protección CSRF. Axios obtiene /sanctum/csrf-cookie y envía X-XSRF-TOKEN. No hay tokens guardados en localStorage; allí únicamente se guarda el ID de la empresa seleccionada, que el backend vuelve a verificar.

## Aislamiento

- El ID de empresa forma parte de la URL y nunca se confía en él sin verificar la membresía.
- Los modelos de negocio se consultan con forOrganization().
- Las claves foráneas compuestas (organization_id, parent_id) impiden relaciones entre empresas incluso mediante SQL directo.
- La Policy comprueba un permiso para el usuario y la empresa concretos. No existe un administrador global implícito.
- Los Resources limitan el JSON. El vendedor no recibe precios de compra.
- Los tests intentan acceder, actualizar y vincular datos de otra empresa.

## Ventas e inventario

SaleService reserva la secuencia bloqueando la organización y luego bloquea productos por ID ascendente. Esta decisión serializa las ventas de una empresa: suficiente para un MVP y más sencilla de razonar. Escalar a miles de operaciones por segundo requeriría medir y revisar este punto.

El servidor toma precios e impuestos del catálogo; el navegador solo muestra un estimado. Money opera en centavos enteros. Los precios unitarios tienen dos decimales y el impuesto se redondea HALF_UP por línea. Se guardan nombres, SKU, precios e impuestos históricos.

request_key es un UUID generado por el cliente. Una repetición con el mismo contenido devuelve la misma venta; reutilizar la clave con otro contenido produce 409. Para un reintento tras un problema de red se conserva la clave. Stock, venta, líneas y movimientos se confirman o revierten juntos.

Una anulación conserva venta y líneas y agrega movimientos inversos. Anular dos veces no duplica el reintegro. Si el producto se archivó tras quedarse sin unidades, se restaura para que el stock devuelto sea visible. No existen endpoints de borrado o edición de movimientos históricos.

products.current_stock es un saldo materializado; inventory_movements es el historial. El campo stock no está en fillable ni se acepta por CRUD. Las restricciones CHECK de PostgreSQL impiden stock e importes negativos.

## Patrones elegidos

- Services para coordinar transacciones y reportes, no para envolver cada llamada Eloquent.
- Form Requests para validar entradas.
- API Resources para decidir el contrato público.
- Policy por organización y permisos fijos sembrados en la base.
- Enums para tipos de movimiento, estado de venta y roles.
- SaleCompleted se publica después del commit. Su listener en cola escribe un evento estructurado de operación, con reintentos. Nunca altera stock.
- Sin Repository genérico, microservicios ni DTOs que solo copian un array pequeño.

## Tiempo y estado

Laravel y cada conexión PostgreSQL utilizan UTC. Los indicadores diarios y mensuales respetan la zona horaria de la organización. La V1 crea ventas completadas directamente; los borradores son una ampliación futura. No hay pagos, facturación fiscal ni múltiples almacenes.

## Límites deliberados

Roles globales fijos; membresías por empresa; cuentas existentes no se pueden incorporar sin un futuro proceso de invitación aceptada. La V1 crea usuarios nuevos para el equipo. Dashboard con últimos 30 días y rankings mensuales. Sin IA ni RAG todavía. Google Fonts es opcional y tiene fuentes locales de respaldo.

Laravel 12 se conserva por el PHP 8.2 instalado. Su soporte de seguridad finaliza el 24-02-2027 según [la política oficial](https://laravel.com/docs/12.x/releases); antes de publicar a largo plazo se debe migrar a PHP 8.3+ y Laravel 13.
