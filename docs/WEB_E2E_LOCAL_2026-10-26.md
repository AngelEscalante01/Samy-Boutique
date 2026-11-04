# Samy web — recorrido E2E local (26 de octubre de 2026)

Entorno: `compose.local.yaml`, `http://127.0.0.1:18080`, base `samy_local`, MinIO local para imágenes y Mailpit para correo. Todas las operaciones comerciales descritas abajo se hicieron desde la interfaz del navegador con el gerente local. La plantilla se llenó en Excel por su interfaz. No se modificó producción.

| Flujo | Acción y comprobación visible | Resultado |
| --- | --- | --- |
| Cliente | Alta de `E2E Prueba Boutique 26 Oct 2026`, edición y consulta de ficha | ID 3; listado muestra 3 clientes tras corregir paginación |
| Producto | Alta de vestido con variante M/Negro, stock 5; edición y fotos | ID 8, SKU `VES-000006`, variante `VES-000007`; 2 imágenes |
| Fotos masivas | Filtro de últimos 7 días y segunda imagen desde tabla | `Fotos guardadas`; portada y galería visibles |
| Etiquetas | Impresión individual desde producto y selección múltiple en tabla | El navegador recibió la solicitud; salida física pendiente de la impresora |
| Excel nuevo | Descarga, edición en Excel con listas de catálogo, carga, vista previa y confirmación | Lote `Samy-E2E-26-Oct.xlsx` completado; producto ID 9 con 2 variantes; foto agregada después |
| Excel actualización | Descarga de productos existentes, recarga, vista previa y confirmación selectiva | Producto ID 9 completado sin alterar stock; 3 productos antiguos de prueba fueron rechazados por catálogos inactivos |
| POS | Búsqueda mientras se escribe, lector simulado por código, cambios de cantidad y descuentos | Los totales cambiaron en tiempo real; venta `VE-00003` por $279.00, descuento manual $20.00 |
| Cupón | Alta, edición, filtro de vigencia y uso en POS | `E2E10OCT` aplicado: $349.00 − $34.90 = $314.10, venta `VE-00005` |
| Cancelación de venta | Cancelación de `VE-00005` con restitución de inventario | Estado Cancelada, stock M/Beige volvió a 3; el cupón cuenta 0 usos de ventas completadas |
| Apartado principal | Alta, cambio de cliente, sustitución y adición de prendas, vigencia, abono mixto, liquidación y entrega | `AP-00002`, venta de liquidación `VE-00004`; saldo 0 |
| Excedente y devolución | `AP-00003`: dos prendas $648.00, abono $500.00; retirar una, devolver $100.00 y $101.00 | Total $299.00, excedente $201.00 devuelto, aplicado neto $299.00; liquidado y entregado como `VE-00006` |
| Límite de devolución | Intento de registrar $102.00 cuando quedaban $101.00 | Formulario lo bloqueó |
| Corte diario | Generación y guardado del corte; nueva vista previa después de devoluciones | Corte guardado inicialmente: $1,376.00 y 1 cancelación. Vista previa posterior: $1,675.00, 3 ventas, 1 cancelación, $985.00 de ganancia; pagos netos por método suman $1,675.00 |
| Reportes | Ventas, productos, clientes, periodo y cancelación | Totales y filas diarias visibles; octubre $1,679.00 con 5 ventas y 1 cancelación después de `VE-00006` |
| Catálogos | Crear y renombrar categoría | `Accesorios prueba E2E`, prefijo AEE, creada y actualizada |
| Ocasiones o estilos | Crear `Casual`, marcarlo en el producto importado y volver a abrir la edición | El estilo permaneció seleccionado tras recargar el producto ID 9 |
| Carrusel público | Crear una diapositiva con imagen desde el formulario; editar texto, orden e imágenes de escritorio y teléfono desde la misma pantalla; desactivarla y recargar el catálogo | Imagen y texto actualizados visibles en el catálogo; la ruta móvil distinta quedó en el elemento `picture`; al desactivar, desapareció del catálogo. Después se activaron dos diapositivas sintéticas solo en local para probar el nuevo diseño y su navegación |
| Catálogo público | Apertura de la ficha publicada, galería, talla, color y stock | Producto ID 8 con dos fotos y stock visible |
| Usuarios, ajustes y avisos | Navegación y lectura de pantallas | Un gerente activo; push local inhabilitado por falta de VAPID |

## Fallos corregidos durante el recorrido

- La edición de cupones recibía el recurso anidado y mostraba `undefined` y campos vacíos.
- El contador de cupones y el de clientes mostraban cero con filas existentes.
- Los filtros de vigencia de cupones enviaban parámetros que el servidor ignoraba.
- Reportes mostraba cero vendido y cero ganancia por una diferencia entre nombres de campos enviados y los que leía Vue.
- La confirmación de venta o liquidación decía que faltaba el folio para imprimir porque las claves flash de impresión no se compartían con Inertia.
- Un cupón de una venta cancelada seguía consumiendo su cuota de usos. Ahora se cuentan solo ventas completadas y se conserva el movimiento para auditoría.
- El botón de guardar corte permitía intentar una segunda grabación para la misma fecha sin explicar que el corte histórico era inmutable. Ahora desactiva el botón y muestra el motivo; la vista previa sigue calculando las cifras actuales.
- El intento anterior de subir una imagen al carrusel usó una ruta de Linux que el navegador de Windows no podía abrir; no llegó al servidor. Con una ruta local de Windows, la carga funcionó. La edición del carrusel, que antes dependía de ventanas emergentes, ahora tiene un formulario integrado para texto, destino, orden, estado e imágenes. Una prueba automatizada adicional comprobó el reemplazo de imagen y la limpieza de la anterior (3 pruebas del carrusel, 55 aserciones).

## Pendiente o fuera de esta prueba

- Exportaciones de Reportes: resuelto el 29 de octubre; ver el seguimiento de PDF y Excel al final de este documento.
- No se verificó impresión física ni lectura Bluetooth. Las solicitudes de impresión web llegaron a la capa de impresora, que indicó “Impresora Android no disponible en este dispositivo”.
- La caché de fotos sin conexión en el POS informó que omitió 4 enlaces de fotos; la visualización web conectada y el catálogo público sí mostraron las imágenes. Validar la política de caché en dispositivo más adelante.
- No se ejercitaron push ni envío real de correos a destinatarios. El entorno local usa Mailpit y tiene push deshabilitado.
- La retención/borrado automático de imágenes tras 15 días sigue pospuesta por decisión del usuario hasta conciliar operaciones offline de Android.
- El corte guardado de hoy conserva la primera instantánea; el sistema impide sobrescribir un corte histórico. La vista previa posterior incluye la última operación E2E.

Este recorrido comprueba los caminos indicados y sus resultados visibles. No sustituye una matriz exhaustiva de todas las validaciones, permisos, estados concurrentes y hardware.

## Ajuste visual del catálogo público

Se trasladaron búsqueda, orden y filtros arriba del carrusel. En escritorio, género, categoría, talla, color, estilo y orden aparecen como navegación en una sola fila; al pasar el cursor o enfocar un filtro se abre su panel de opciones. En móvil, el icono de menú abre una lista de filtros con secciones desplegables. El banner ocupa todo el ancho de la ventana, usa proporción 3:1 en escritorio y 4:5 en teléfono, y cambia de diapositiva con deslizamiento automático cada 6.5 segundos o con gesto manual. Las flechas de escritorio se muestran al pasar el cursor o enfocar el carrusel. El módulo de administración acepta imágenes separadas de 1800 × 600 px y 800 × 1000 px, con vistas previas; si falta la móvil, recorta la de escritorio. Se verificaron con clics los menús y filtros de escritorio y móvil. Dos banners E2E quedan activos únicamente en la base local como vista previa.

## Reportes PDF y Excel · 29 de octubre de 2026

Los botones descargan archivos reales con las tres secciones del reporte y el rango aplicado. La pantalla y las exportaciones comparten el mismo origen de datos. El Excel contiene Ventas, Productos y Clientes; preserva importes como números y SKU/teléfonos como texto. El PDF incluye resumen, detalle diario, rankings, fecha de generación, moneda y paginación. La exportación conserva los límites de los rankings de la pantalla; próximos a fidelidad representa el estado actual del cliente. La ganancia utiliza los costos actuales, igual que la pantalla.

Ambas rutas exigen `reports.view`. Las fechas inválidas o invertidas se rechazan; cambiar fechas sin aplicar deshabilita las descargas y muestra una indicación. No se modifican ventas, inventario ni cortes. Se añadió Dompdf 3.1.6 con acceso remoto, PHP y JavaScript deshabilitados para el PDF.

Verificación: suite SQLite en PHP 8.4, 147 casos, 1,401 aserciones y 3 omitidos; build Vite/PWA y despliegue local PHP 8.2 correctos. Se abrió un archivo de muestra en Excel sin reparación y se renderizaron todas las páginas del PDF. En el navegador autenticado se descargaron PDF y XLSX del 1 al 29 de octubre; después se aplicó solo el 26 de octubre y se descargó un Excel con una fila diaria, tres ventas y total de $1,675.00, coincidente con la pantalla. La cuenta temporal y sus sesiones se retiraron al terminar. No se modificó producción.
