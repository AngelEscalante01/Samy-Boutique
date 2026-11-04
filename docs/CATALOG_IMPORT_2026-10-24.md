# Importación y actualización Excel de productos

Implementación local. No se ejecutaron importaciones sobre la base comercial real.

## Uso

Desde **Productos**, abrir **Importar Excel** (también disponible desde Nuevo producto), o `/catalog-imports`. En esa pantalla, **Descargar plantilla Excel** obtiene el archivo personalizado y **¿Cómo uso la plantilla?** abre la explicación del formato y los pasos sin salir de la página. El archivo incluye Productos, Instrucciones, Ejemplos y Referencias. Categorías con prefijo SKU, tallas, colores y ocasiones activos se consultan al descargar y aparecen como listas desplegables en Productos; género también tiene lista. Las categorías sin prefijo se muestran aparte en Referencias para configurarlas antes de importar. Si un catálogo cambia, descarga una plantilla nueva. El usuario autorizado a ver costos recibe la versión con costo; los demás reciben una plantilla sin esa columna.

Una fila representa una variante. En la plantilla nueva, la primera fila de cada modelo lleva nombre, categoría y género; las filas contiguas con nombre vacío son variantes del mismo modelo y heredan sus datos comunes. Escribir otro nombre comienza otro producto. Así no se capturan códigos ni grupos. La columna `grupo_producto` de plantillas anteriores sigue aceptada para compatibilidad. Un producto simple usa una fila y talla/color existentes conforme al esquema actual; no se crean catálogos automáticamente. La hoja Ejemplos usa opciones vigentes al descargar y muestra dos variantes si existen dos combinaciones talla/color; con una sola combinación muestra una variante y explica cómo ampliar el catálogo. La hoja no se importa. Las cantidades son enteros no negativos y los importes usan punto decimal con hasta dos decimales. `ocasion_1`, `ocasion_2` y `ocasion_3` ofrecen tres desplegables independientes por producto. Más asociaciones se agregan en la ficha del producto, sin límite de tres. La columna antigua `ocasiones` con nombres separados por coma sigue aceptada al importar archivos anteriores.

1. Cargar XLSX y revisar. Esta operación crea únicamente el lote y sus grupos, sin productos, códigos ni stock.
2. Seleccionar expresamente grupos y confirmar. Se revalidan referencias y permisos. Cada grupo se procesa atómicamente: producto, variantes, stock inicial, registro SKU y estado del grupo.
3. Consultar avance o regresar desde Mis lotes recientes. Los grupos válidos pueden completarse aunque otros sean inválidos.
4. Abrir **Agregar fotos al lote en tabla**. La tabla muestra una fila por producto, sus fotos actuales y un selector para varias fotos nuevas; cada foto puede asociarse con una variante. Guarda una fila o todas las pendientes. Esta pantalla solo agrega fotos: los demás datos se editan en cada producto o con el Excel. Desde Productos, **Fotos en tabla** abre el inventario completo en páginas de 50 productos, con búsqueda por nombre o código. La importación Excel no adjunta imágenes.

El modo `update` se inicia con **Exportar productos para actualizar**. Cada fila incluye `producto_id`, `variante_id` y `version_producto`; no se infieren coincidencias por nombre. Se importan variantes nuevas dejando `variante_id` vacío y capturando talla, color y precio. Comienzan con stock cero; el stock existente no se sobrescribe. Una celda vacía conserva el dato vigente, incluso ocasiones y costo. La exportación muestra las primeras tres ocasiones por ID; al cambiar esas columnas, las asociaciones adicionales se conservan. Las adicionales se editan en la ficha del producto. El grupo debe incluir todas las variantes existentes del producto. Al confirmar se verifica la versión del producto y los permisos; si cambió, se descarga una exportación nueva. El lote conserva identidad por usuario, modo y hash del archivo. Tras completar, el enlace abre el editor masivo del lote.

## Reintentos y seguridad

El hash SHA-256 del archivo, usuario y modo identifica el lote; recargar los mismos bytes con el mismo usuario recupera el lote anterior. No se deduplican archivos diferentes ni cargas entre usuarios distintos. Tras una importación parcial, un archivo corregido debe contener solamente los grupos aún no creados; la pantalla y plantilla lo explican.

La fila del grupo se bloquea durante el trabajo. Un grupo completado es un no-op incluso al reentregar un job; sus códigos y existencias no se vuelven a crear. Los fallos de validación dejan un resultado visible; se puede reintentar desde el lote después de corregir referencias/permisos. Los cambios a los valores del archivo requieren una nueva carga. Los fallos inesperados tienen tres intentos y luego un mensaje público genérico; el detalle queda en el registro del servidor.

Los lotes solo son visibles/confirmables por su propietario con `products.create`. Se revisan estado activo y permisos al ejecutar el job; los costos requieren `products.view_purchase_price` también en confirmación/ejecución. Si el permiso se retira, la consulta oculta los costos guardados. Estos datos viven en la base y requieren su control de acceso y respaldo habitual.

El parser usa ZipArchive y SimpleXML con `LIBXML_NONET`; no extrae rutas ni evalúa fórmulas. Rechaza macros, objetos embebidos, enlaces externos, DTD/entidades, fórmulas en Productos, filas/celdas repetidas y referencias inválidas. Límites: archivo de 5 MB, 1000 filas de datos, 64 columnas, 1000 entradas ZIP, 8 MB por entrada y 20 MB descomprimidos totales. Solo se admite XLSX, no XLS, XLSM ni CSV. Los textos exportados a referencias son celdas de texto OOXML, no fórmulas.

## Operación

Aplicar la migración `2026_10_24_000011_create_catalog_imports.php` sobre una copia validada junto con la del registro SKU. Las categorías deben tener prefijo configurado y los conflictos históricos de códigos deben estar conciliados.

Los jobs fuerzan conexión `database` y cola `catalog-imports`, incluso si el correo u otras tareas usan `sync`. El worker debe escuchar esa cola. Timeout del job: 60 segundos; `retry_after` de la conexión debe ser mayor (90 segundos en la configuración actual). No se requiere Composer adicional: PHP necesita extensiones zip y SimpleXML.

`php artisan catalog:resume-imports` reencola grupos confirmados pendientes durante más de cinco minutos. Permite recuperarse de un fallo entre guardar la confirmación y despachar el trabajo. Es seguro reentregar gracias al bloqueo y al estado completed. Programarlo con exclusión mutua cada cinco minutos y supervisar jobs fallidos.

## API

Con Sanctum y `products.create` para creación; actualización requiere también `products.update`:

- `GET /api/catalog-imports/template`
- `POST /api/catalog-imports/preview` multipart: `mode=create|update`, `file=<xlsx>`
- `GET /api/catalog-imports/{batch}`
- `POST /api/catalog-imports/{batch}/confirm` JSON: `{"groups":[1,2]}`

Las rutas web equivalentes bajo `/catalog-imports` usan sesión/CSRF. La confirmación devuelve 202; se consulta el lote para obtener los resultados. La API está disponible para un cliente Android futuro; esta entrega no incluye esa pantalla Flutter.

`GET /catalog-imports/export-update` genera el XLSX de actualización con IDs y versión; acepta `ids[]` para limitar la selección y restringe la exportación a 500 productos/1000 variantes. Los costos se exportan únicamente con el permiso correspondiente. La importación de actualización no permite vaciar campos mediante una celda en blanco ni modificar stock; esas operaciones usan sus flujos de edición e inventario.

## Validación local

En la base local de demostración, `php artisan db:seed --class=LocalDemoCatalogSeeder` agrega Vestidos (prefijo VES), tallas M/L y colores Negro/Beige. Desactiva las tres opciones técnicas `Smoke*` sin borrar los productos de prueba existentes. El seeder rechaza otros entornos.

Fixtures SQLite prueban vista previa sin inventario, producto simple/múltiples variantes, grupos inválidos aislados, mismo archivo repetido, jobs repetidos, permisos de propietario/costos, catálogo cambiado y permiso retirado después de revisar, rechazo de fórmulas/macros/entidades y rollback después de crear variantes. Las plantillas se generaron con Artifact Tool y se revisaron visualmente. La plantilla descargada abrió en Microsoft Excel sin aviso de reparación y se comprobaron los desplegables de categoría y talla con datos del catálogo local. No se afirma ensayo concurrente MySQL.
