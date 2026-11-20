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
- Conectar el Tool Calling existente a un proveedor LLM mediante Laravel AI SDK y crear la experiencia de chat en React.
- Añadir RAG multiempresa: documentos, extracción en cola, embeddings, búsqueda vectorial, citas y autorización por organización.
- Implementar múltiples almacenes siguiendo `docs/multi-warehouse-roadmap.md`; actualmente solo existe el diseño.
- Configurar correo real, dominio, HTTPS y pruebas de entrega para verificación, invitaciones, recuperación y alertas de stock.
- Aprovisionar y probar Azure: Container Apps, PostgreSQL, ACR, Key Vault, OIDC, migraciones y rollback.
- Incorporar observabilidad en producción: métricas, trazas, alertas, fallos de Jobs, copias de seguridad y restauración comprobada.
- Ejecutar pruebas de carga y usar sus resultados para decidir índices y optimizaciones; completar una auditoría de seguridad externa.
- Definir el alcance de negocio pendiente: compras a proveedores, pagos, facturación fiscal, múltiples monedas y roles personalizados.

El backend de Tool Calling ya está implementado con cuatro herramientas, permisos y aislamiento multiempresa. Todavía no existe un asistente conectado a un modelo, Laravel AI SDK, RAG, embeddings ni agentes autónomos.

## Primera tarea para estudiar lo construido

**Objetivo:** defender la atomicidad de una venta.

**Aprender:** transacciones, bloqueos, validación y redondeo.

**Archivos:** app/Services/SaleService.php, app/Services/InventoryService.php y tests/Feature/SalesInventoryTest.php.

**Concepto:** si una línea falla, la operación completa debe volver a su estado anterior; dos peticiones no pueden vender la misma última unidad.

**Tu tarea:** ejecuta los tests; lee create() de SaleService y explica con tus palabras por qué se bloquea la empresa antes que los productos. Después añade un test para un producto inactivo sin cambiar el servicio.

**Criterios:** respuesta 422, stock sin cambios y ninguna venta creada.

**Pregunta de entrevista:** ¿Por qué validar stock antes de abrir la transacción no evita una sobreventa concurrente?
