# Arquitectura y Roadmap: Múltiples Almacenes (Multi-Warehouse)

Este documento detalla el diseño técnico y el plan de migración para soportar múltiples almacenes o bodegas por empresa en una etapa posterior de **SmartBusiness_AI_V1**.

---

## 1. Modelo de Datos Propuesto

### 1.1 Tabla `warehouses` (Almacenes / Bodegas)
```sql
CREATE TABLE warehouses (
    id BIGSERIAL PRIMARY KEY,
    organization_id BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(50) NOT NULL,
    address VARCHAR(255) NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_warehouses_org_code UNIQUE (organization_id, code),
    CONSTRAINT uk_warehouses_org_default UNIQUE (organization_id, is_default) WHERE (is_default = TRUE)
);
```

### 1.2 Tabla `warehouse_stock` (Stock Segmentado)
El stock pasa de ser únicamente un saldo centralizado en `products.current_stock` a estar desglosado por almacén:
```sql
CREATE TABLE warehouse_stock (
    id BIGSERIAL PRIMARY KEY,
    organization_id BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    warehouse_id BIGINT NOT NULL REFERENCES warehouses(id) ON DELETE CASCADE,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    current_stock INTEGER NOT NULL DEFAULT 0,
    minimum_stock INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_warehouse_product UNIQUE (warehouse_id, product_id),
    CONSTRAINT chk_warehouse_stock_non_negative CHECK (current_stock >= 0)
);
```

### 1.3 Modificaciones a Tablas Existentes
- **`inventory_movements`**:
  - Agregar `warehouse_id BIGINT NOT NULL REFERENCES warehouses(id)`
  - Agregar `destination_warehouse_id BIGINT NULL REFERENCES warehouses(id)` (para traslados)
- **`sales` y `sale_items`**:
  - Agregar `warehouse_id BIGINT NOT NULL REFERENCES warehouses(id)` a nivel de venta o de item para saber de cuál bodega se descontó el inventario.

### 1.4 Nueva Tabla `warehouse_transfers` (Traslados entre Bodegas)
Para gestionar el ciclo de vida de transferencias de inventario entre sucursales:
- `id`, `organization_id`, `from_warehouse_id`, `to_warehouse_id`, `status` (`pending`, `completed`, `cancelled`), `notes`, `requested_by_user_id`, `completed_at`.
- `warehouse_transfer_items`: `transfer_id`, `product_id`, `quantity`.

---

## 2. Lógica de Negocio y Transaccionalidad

### 2.1 Prevención de Deadlocks y Concurrencia
Al realizar movimientos o traslados entre bodegas:
1. Bloquear siempre los registros en orden determinista: ordenar por `warehouse_id ASC, product_id ASC`.
2. Adquirir lock pesimista: `WarehouseStock::where(...)->lockForUpdate()->first()`.
3. Mantener `products.current_stock` como la suma consolidada para lectura rápida en catálogos y búsquedas, o derivarlo vía vista materializada/trigger.

### 2.2 Estrategia de Migración hacia Multi-Almacén
Para asegurar retrocompatibilidad con las instalaciones existentes:
1. **Paso 1:** Al correr la migración de `warehouses`, crear automáticamente un almacén inicial para cada organización existente: `name: 'Almacén Principal'`, `code: 'ALM-01'`, `is_default: true`.
2. **Paso 2:** Poblar `warehouse_stock` con los valores actuales de `current_stock` y `minimum_stock` de cada producto.
3. **Paso 3:** Asignar en `inventory_movements` históricos el `warehouse_id` del almacén principal por defecto.
4. **Paso 4:** Actualizar `InventoryService` para aceptar `warehouse_id` (tomando el almacén por defecto si se omite).

---

## 3. Impacto en Permisos y Roles
- Nuevo permiso `warehouses.read` y `warehouses.write` (admin / manager).
- Nuevo permiso `transfers.create` y `transfers.approve`.
- Posibilidad futura de restringir vendedores a un almacén específico (`assigned_warehouse_id` en membresía).