# Backlog pendiente para Antigravity

Este documento contiene únicamente trabajo pendiente. Tool Calling, ciclo de autenticación, mejoras de inventario, seguridad, Docker E2E, CI y actualización a PHP 8.3+ / Laravel 13 (P0) ya están implementados y no deben reconstruirse.

## Modelo recomendado

- **Antigravity:** `Gemini 4 Argon (High)` para arquitectura, migraciones, seguridad y cambios que atraviesen backend, frontend e infraestructura.
- **Alternativa rápida:** `Gemini 3.8 Flash` para documentación, pruebas y cambios mecánicos de bajo riesgo.
- **Asistente de SmartBusiness:** comenzar con `Gemini 3.8 Flash`, pero mantener proveedor y modelo configurables mediante variables de entorno y Laravel AI SDK. No escribir el nombre del modelo directamente en controladores o Services.

En Antigravity CLI, cuando esté disponible, seleccionar el modelo con:

```text
/model gemini-4-argon-high
```

## Reglas de trabajo

1. Trabajar únicamente sobre `main`; no crear ramas nuevas.
2. Antes de editar, ejecutar `git pull --ff-only origin main` y confirmar que el árbol está limpio.
3. Completar una sola mejora por ciclo. No intentar resolver todo este backlog en un único cambio.
4. Conservar la arquitectura actual: Controllers delgados, Form Requests, Policies, API Resources y lógica en Services.
5. Toda consulta y escritura debe estar limitada por `organization_id`; agregar pruebas de acceso entre empresas.
6. Reutilizar `ToolRegistry`, `ToolExecutor`, `SaleService`, `InventoryService` y `ReportingService`. No duplicar lógica empresarial dentro de agentes o herramientas de IA.
7. No guardar secretos, tokens ni archivos `.env` en Git. Añadir únicamente nombres de variables a `.env.example`.
8. Antes de cada commit ejecutar Pint, PHPUnit, Vitest y el build del frontend. Ejecutar Docker smoke cuando cambie infraestructura.
9. Actualizar README, API, arquitectura y este backlog al terminar cada mejora.
10. Usar un commit corto por mejora y hacer push a `main` solo cuando todas las comprobaciones pasen.

## Orden de ejecución

### P1 — Asistente con Laravel AI SDK

**Objetivo:** conectar el Tool Calling existente a un modelo real sin romper permisos ni aislamiento multiempresa.

**Indicaciones para Antigravity:**

- Instalar el paquete oficial `laravel/ai`, publicar configuración y migraciones necesarias.
- Crear un agente empresarial y adaptar las cuatro herramientas existentes al contrato del SDK; la ejecución final debe seguir pasando por los Services actuales.
- Guardar conversaciones asociadas a organización y usuario. Verificar la membresía en cada lectura y escritura.
- Añadir endpoints versionados para crear, listar, continuar y eliminar conversaciones.
- Las acciones mutables —ajustar stock, crear venta y anular venta— deben requerir confirmación explícita antes de ejecutarse.
- Implementar streaming si el SDK y la arquitectura actual lo permiten sin introducir un servicio adicional innecesario.
- Usar fakes del SDK en tests; CI nunca debe consumir una API de pago.

**Criterios de aceptación:** consultas de dashboard mediante lenguaje natural, propuesta y confirmación de acciones, rechazo por permisos, aislamiento entre empresas, manejo de proveedor no configurado y tests sin red.

### P2 — Interfaz React del asistente

**Objetivo:** añadir una experiencia de chat profesional y segura.

**Indicaciones para Antigravity:**

- Crear ruta `/assistant`, listado de conversaciones, mensajes en streaming y estados de carga/error.
- Mostrar claramente cuándo el modelo quiere ejecutar una acción, sus argumentos y botones de confirmar o cancelar.
- No representar una acción como completada hasta recibir confirmación del backend.
- Mantener diseño responsive, accesibilidad por teclado y mensajes comprensibles.

**Criterios de aceptación:** login, cambio de empresa, conversación persistente, recarga sin pérdida, confirmación de herramientas y pruebas de componentes.

### P3 — RAG multiempresa

**Objetivo:** responder preguntas basadas en documentos propios de cada organización con citas verificables.

**Indicaciones para Antigravity:**

- Usar PostgreSQL con `pgvector` y las capacidades de embeddings del Laravel AI SDK.
- Modelar documentos y fragmentos con `organization_id`, estado de procesamiento, metadatos, checksum y trazabilidad.
- Procesar extracción y embeddings mediante Jobs idempotentes; limitar tipo y tamaño de archivo.
- Filtrar la búsqueda vectorial por organización antes de calcular o devolver resultados.
- Incluir citas con documento y fragmento; responder que no hay evidencia cuando la recuperación sea insuficiente.
- Implementar eliminación completa del documento y sus embeddings.

**Criterios de aceptación:** ingestión, reintentos, citas, eliminación, cero filtraciones entre empresas y pruebas con embeddings falsos.

### P4 — Correo y notificaciones reales

**Objetivo:** hacer operativos verificación, recuperación, invitaciones y alertas de stock fuera del entorno local.

**Indicaciones para Antigravity:**

- Configurar un proveedor mediante variables de entorno y secretos; no fijar credenciales en el código.
- Añadir plantillas consistentes, colas, reintentos y registro de fallos sin exponer tokens.
- Documentar SPF, DKIM y DMARC como pasos manuales del dominio.

**Criterios de aceptación:** pruebas con Mail fake, entrega comprobada en staging, enlaces HTTPS correctos y Jobs fallidos observables.

### P5 — Azure y operación en producción

**Objetivo:** publicar un entorno reproducible y recuperable.

**Indicaciones para Antigravity:**

- Aprovisionar o documentar Container Apps, ACR, PostgreSQL, Key Vault, identidad administrada y OIDC con privilegios mínimos.
- Ejecutar migraciones mediante el Job existente; nunca usar `migrate:fresh` en cloud.
- Configurar dominio, TLS, health checks, logs, escalado, presupuesto y alertas.
- Definir rollback de aplicación y restauración de PostgreSQL; probar ambos procedimientos.

**Criterios de aceptación:** despliegue desde GitHub Actions, smoke test sobre HTTPS, secretos fuera del repositorio y evidencia de rollback/restauración.

### P6 — Observabilidad, rendimiento y seguridad

**Objetivo:** medir el comportamiento real antes de optimizar.

**Indicaciones para Antigravity:**

- Añadir correlation IDs, métricas de latencia/error, duración de Jobs, llamadas al modelo, tokens y coste estimado.
- Alertar por errores 5xx, Jobs fallidos, base no disponible y consumo anormal de IA.
- Ejecutar pruebas de carga sobre ventas, dashboard, inventario y chat; añadir índices solo con evidencia.
- Ejecutar análisis SAST/DAST y documentar hallazgos, riesgo, corrección y prueba de regresión.

**Criterios de aceptación:** dashboard operativo, alertas comprobadas, informe de carga reproducible y pipeline de seguridad en verde.

### P7 — Múltiples almacenes

**Objetivo:** implementar el diseño de `docs/multi-warehouse-roadmap.md` después de estabilizar producción.

**Indicaciones para Antigravity:** seguir una migración expand/contract, crear almacén principal para datos existentes, bloquear filas en orden estable y mantener trazabilidad de transferencias. No agregar la funcionalidad mediante una columna opcional improvisada en `products`.

**Criterios de aceptación:** stock por almacén y consolidado, transferencias atómicas, concurrencia, compatibilidad con ventas existentes y pruebas de migración con datos previos.

### P8 — Alcance empresarial adicional

Estas mejoras necesitan primero decisiones funcionales del propietario y no deben implementarse por suposición:

- Compras y órdenes a proveedores.
- Pagos y cuentas por cobrar.
- Facturación fiscal según país.
- Múltiples monedas y reglas de conversión.
- Roles y permisos personalizados.

Antes de programar cualquiera, Antigravity debe producir una propuesta de alcance, modelo de datos, endpoints, riesgos y criterios de aceptación para aprobación humana.

## Definición de terminado

Una mejora solo se elimina de este backlog cuando el código, migraciones, permisos, pruebas, documentación y validación manual están completos en `main`. Una maqueta, un documento de diseño o un endpoint sin interfaz no cuentan como funcionalidad terminada.
