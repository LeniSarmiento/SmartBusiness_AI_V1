# Estrategia de pruebas

SmartBusiness separa las comprobaciones por alcance. Todas las pruebas PHP que usan datos apuntan a `smartbusiness_test`; nunca deben ejecutarse contra la base local `smartbusiness`.

## Niveles

### Unit

Prueban funciones pequeñas sin iniciar Laravel ni conectarse a PostgreSQL. `MoneyTest` comprueba conversión a centavos, redondeo, límites y formatos inválidos. En React, `saleMath.test.ts` comprueba el cálculo previo que ve el usuario; el total definitivo siempre lo calcula Laravel.

```powershell
.\scripts\php.ps1 vendor/bin/phpunit --testsuite Unit
npm --prefix frontend test
```

### Integration

Inician Laravel y conectan servicios reales con PostgreSQL. Comprueban que `InventoryService` mantenga sincronizados el saldo y el historial, que rechace cambios fuera de una transacción y que `SaleService` guarde líneas, movimientos y eventos. También verifican rollback completo cuando falla una línea.

```powershell
.\scripts\php.ps1 vendor/bin/phpunit --testsuite Integration
```

### Functional

Ejecutan el sistema a través de la API HTTP como lo haría la SPA. El recorrido principal registra una empresa, crea categoría, proveedor, cliente y producto, recibe stock, vende, consulta historial y dashboard, anula la venta y confirma la devolución de existencias.

```powershell
.\scripts\php.ps1 vendor/bin/phpunit --testsuite Functional
```

### Feature y concurrencia

Las pruebas Feature cubren endpoints, autenticación, autorización y aislamiento entre empresas. `ConcurrencyTest` inicia dos procesos que intentan vender la última unidad: solo uno puede completar la venta.

## Suite completa

```powershell
.\scripts\php.ps1 vendor/bin/phpunit
php vendor/bin/pint --test
npm --prefix frontend test
npm --prefix frontend run build
```

Las suites que reconstruyen tablas deben ejecutarse secuencialmente sobre una misma base. GitHub Actions ejecuta la suite completa sobre un servicio PostgreSQL desechable.

## Pruebas exploratorias de interfaz

Antes de publicar una versión se comprueba manualmente en navegador:

1. Login de administrador, gerente y vendedor.
2. Menús visibles según permisos.
3. Flujo de venta y anulación.
4. Ocultamiento del precio de compra para vendedores.
5. Redirección al intentar abrir una sección no autorizada.
6. Consola del navegador sin errores de aplicación.

Estas comprobaciones complementan las pruebas automatizadas; no las reemplazan.

## Pruebas de rendimiento

`tests/Performance/k6/smoke.js` reutiliza una sesión autenticada y mide dashboard, productos, inventario y ventas sin modificar datos. Los umbrales iniciales fallan el proceso cuando los errores alcanzan 1 % o la latencia p95 excede los límites documentados. CI ejecuta una muestra breve después del Docker E2E; las pruebas prolongadas deben ejecutarse en staging. Consulta `docs/observability.md`.
