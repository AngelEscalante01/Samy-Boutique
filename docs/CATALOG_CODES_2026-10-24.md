# Códigos estables del catálogo

Implementación local, sin despliegue ni migración sobre la base productiva.

## Contrato

- El alta web genera un SKU para el producto y otro para cada variante, desde un contador compartido por prefijo: `VES-000001`, `VES-000002`, etc. Varias unidades de la misma variante comparten código.
- Configurar el prefijo en Catálogos > Categorías. Sugerencias editoriales: VES para vestidos, BLU para blusas, PAN para pantalones; no se asignan automáticamente a categorías históricas. Se aceptan 2–12 letras/números ASCII, iniciando por letra.
- Cambiar nombre, precio, categoría o prefijo no modifica códigos ya emitidos. La edición web rechaza un SKU diferente. Un prefijo retirado permanece vinculado a su categoría original y no puede trasladarse a otra.
- `catalog_codes` reserva globalmente códigos y alias de productos/variantes. No tiene borrado en cascada. La clave única es SHA-256 del texto normalizado mediante trim y mayúsculas Unicode. Los valores históricos originales se conservan.
- `CatalogCodeService::next()` y `bind()` deben ejecutarse dentro de la misma transacción que crea el producto/variante. La fila única de `catalog_code_state` serializa emisores y administradores de prefijos; el índice único del registro respalda la unicidad. `addAlias()` reserva un valor libre para una entidad existente. Importaciones futuras deben usar este servicio, nunca insertar SKU por su cuenta.
- Una variante omitida al editar se desactiva en lugar de borrarse. Mantiene sus relaciones, stock almacenado y códigos; deja de ser vendible. La edición muestra variantes activas. Restauración explícita de variantes retiradas requiere un flujo posterior.

## Migración y códigos anteriores

`2026_10_24_000010_create_catalog_code_registry.php` crea las tablas y registra SKU existentes por lotes. No cambia ningún SKU comercial. Si un valor normalizado pertenece a varias entidades, conserva la primera reserva y registra las entidades adicionales en `catalog_code_conflicts`. Mientras existan conflictos sin resolver se bloquea la emisión de códigos nuevos; ventas y consulta de productos existentes continúan.

Antes de aplicar en una copia real: respaldo consistente, conteos de productos/variantes, auditoría de códigos repetidos y plan de conciliación con el responsable del catálogo. No marcar conflictos resueltos sin revisar todas las entidades y sus etiquetas. No se puede garantizar reserva de códigos eliminados antes de disponer de este registro; deben incorporarse los históricos disponibles antes del corte.

Tras configurar los prefijos:

```sh
php artisan catalog:fill-missing-codes
php artisan catalog:fill-missing-codes --apply
```

La primera orden solo informa. La segunda asigna códigos únicamente a filas sin SKU, en transacciones individuales con revalidación y reintento; conserva todos los códigos no vacíos. Repetirla no genera códigos adicionales. Si falta un prefijo o hay conflictos, informa el error. No se ejecutó sobre datos reales durante esta implementación.

La migración no admite `down()` destructivo: eliminar el registro permitiría reutilizar identificadores. Una reversión operativa requiere restaurar el respaldo integral validado, sin mezclar escrituras posteriores.

## Validación y límites

Pruebas SQLite cubren alta web, códigos distintos por padre/variante, edición y cambio de categoría sin renumeración, prefijos retirados, alias y entidades borradas, conflictos, variantes archivadas, rollback y relleno idempotente. La exclusión de escrituras simultáneas en MySQL requiere todavía un ensayo con conexiones concurrentes sobre una copia restaurada; SQLite no lo demuestra.

Este módulo no implementa etiquetas físicas, resolución de alias en lectores/POS ni importación Excel. No afirma compatibilidad con escáner o impresora. Los accesos SQL directos y escrituras de futuras integraciones deben pasar por el servicio; no hay triggers de base que impidan saltárselo.

## Descuento global manual

La venta y su vista previa comparten `AssertGlobalDiscountQuantity`: mínimo dos unidades sumando `qty`, no el número de renglones. La web deshabilita el descuento con una unidad y lo retira al reducir cantidades. API y servidor rechazan solicitudes de descuento global positivo con una unidad. Descuentos de artículo, cupón y fidelidad conservan sus reglas. No se recalcula el historial.

Los apartados actuales no tienen descuento global manual: la liquidación guarda `global_discount_type/value = null`. No se agregó un descuento nuevo ni se modificó `LayawayService` por este requisito.
