# Roadmap y mentoría

## Milestone 1 — Foundation, tenancy and authentication

Implementado localmente: esquema, organizaciones, membresías, roles, permisos, sesiones Sanctum, recuperación de contraseña, validación, aislamiento y pruebas. Este documento es la definición del milestone; no se ha creado un milestone remoto en GitHub.

## Entregas V1

1. Base Laravel y PostgreSQL.
2. Identidad y autorización por empresa.
3. Catálogo y clientes.
4. Ventas e inventario transaccionales.
5. Dashboard y SPA React.
6. Tests, Docker y CI.
7. Publicación en GitHub y Azure, pendiente de cuenta y recursos.

## Siguiente etapa

- PHP 8.3+ / Laravel 13 antes de finalizar el soporte de Laravel 12.
- Invitaciones con aceptación, cambio de contraseña desde perfil y correo verificado.
- Auditoría ampliada e índices a partir de métricas reales.
- Asistente con Tool Calling, permisos del backend y salidas estructuradas.
- RAG separado con documentos, jobs de extracción, embeddings y recuperación filtrada por empresa.
- Alertas con notificaciones y métricas de observabilidad.

La IA no está implementada en esta V1 y no hay respuestas ni datos simulados de un modelo.

## Primera tarea para estudiar lo construido

**Objetivo:** defender la atomicidad de una venta.

**Aprender:** transacciones, bloqueos, validación y redondeo.

**Archivos:** app/Services/SaleService.php, app/Services/InventoryService.php y tests/Feature/SalesInventoryTest.php.

**Concepto:** si una línea falla, la operación completa debe volver a su estado anterior; dos peticiones no pueden vender la misma última unidad.

**Tu tarea:** ejecuta los tests; lee create() de SaleService y explica con tus palabras por qué se bloquea la empresa antes que los productos. Después añade un test para un producto inactivo sin cambiar el servicio.

**Criterios:** respuesta 422, stock sin cambios y ninguna venta creada.

**Pregunta de entrevista:** ¿Por qué validar stock antes de abrir la transacción no evita una sobreventa concurrente?
