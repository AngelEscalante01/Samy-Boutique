# Espacio de trabajo compacto · web y Android

Se sustituyen los márgenes amplios de la revisión visual anterior por una disposición de aplicación: paneles unidos, contenido a todo el ancho y separación interna suficiente para los controles. Se conservan las funciones de los módulos.

- Menú de **240 px CSS en web / 240 dp en Flutter** en todos los módulos, incluido POS. En Android se eliminó la estructura lateral distinta del POS: todos los módulos comparten el mismo contenedor. Se conserva el tema y la lógica propios del contenido POS.
- Cabecera común de 48 px/dp. A menos de 840 px/dp, el menú se guarda en un cajón del mismo ancho para dejar libre la pantalla.
- Sin márgenes exteriores ni límite central de anchura. Tarjetas y paneles principales sin huecos entre ellos, esquinas planas y bordes discretos; campos, etiquetas y botones conservan su espacio interno.
- Los indicadores se alinean a la misma altura. Los productos, el detalle de apartados y los formularios aprovechan las columnas disponibles.
- El menú móvil web permite desplazarse para llegar a sus últimas opciones. Cuando está cerrado, queda fuera del foco y del árbol accesible.

La web utiliza clases de estructura `app-page`, `app-surface`, `app-card-grid`, `app-card-stack` y `app-heading`, limitadas al espacio autenticado. Se mantienen los enlaces, campos, permisos y eventos existentes. El catálogo público y los cuadros de diálogo conservan sus reglas propias.

En Android, `AppLayout` centraliza las medidas del menú y la cabecera. Las pantallas continúan siendo widgets Flutter conectados a la API, sin WebView. El acceso «Mi perfil» abre el formulario nativo correspondiente.

## Comprobación

- 68 pruebas Flutter correctas; nueve casos de diseño, incluida igualdad del menú entre POS y Apartados, tema del POS y ausencia de márgenes exteriores. Vistas comprobadas a 390, 817 y 1280 px; texto al 120 % en teléfono.
- 14 pruebas JavaScript correctas y compilación Vite/PWA correcta.
- Análisis Dart sin errores ni advertencias y APK release compilada, firmada e instalada conservando los datos.
- Recorrido web con datos sintéticos en dashboard, POS, productos, clientes, apartados, detalle, alta, reportes, usuarios y configuración. Comprobación responsive de 390 y 800 px mediante una página temporal con iframe; el iframe se utilizó solo como herramienta de revisión de la web, no forma parte de Android ni del producto.
- Galaxy Tab A9+: cambio entre POS y Apartados con el mismo menú; cajón en vertical, retorno a horizontal y carrito previo conservado. No se registraron ni repitieron cobros.
- La conciliación QA conserva once comprobaciones correctas: cuatro ventas, dos apartados, tres abonos, catorce recibos y existencias `[16, 18, 1]`.

Los recursos compilados se actualizaron únicamente en los contenedores locales de Samy y Android QA. `http://127.0.0.1:18080` devuelve el recurso principal de la compilación actual. La página temporal de revisión, su acceso sintético y el cambio temporal de sesiones QA se retiraron al finalizar.

La APK instalada es `samy-boutique-app/artifacts/Samy-Boutique-Compacto-2026-10-30.apk`, SHA-256 `48377fcfe0bb88a5d6bba44992936e9a9edea7ec980985d33ea1125bb799b88e`.

Evidencia: `samy-boutique-app/docs/evidence/compact-layout/`. Producción y VPS no se modificaron. Impresora y lector físicos siguen pendientes; la limpieza automática de fotografías continúa pospuesta.

## Ajuste posterior: barras de desplazamiento web

Todas las barras verticales y horizontales de la web comparten el estilo: 4 px en Chrome/Edge, extremos en píldora, pista transparente y sin flechas. La píldora es blanca al 38 % de opacidad en el menú oscuro y gris suave en fondos claros (carrito, página, tablas y diálogos). Al interactuar aumenta su opacidad. Firefox utiliza su variante nativa fina con los mismos colores. Se conserva el desplazamiento normal y el comportamiento de los controles.

Vite/PWA compiló correctamente. Se comprobaron en el navegador QA los estilos de la página, el catálogo y el carrito: 4 px en ambos ejes, extremos redondos, pista transparente y botones ocultos. El menú llega a su última opción y el carrito se desplazó hasta el final con una prenda sintética, retirada al terminar; no se registraron ventas. La conciliación conserva once comprobaciones correctas y las mismas ventas, abonos y existencias. Evidencia: `docs/evidence/sidebar-scrollbar/`. Recursos actualizados solo en los dos entornos locales; Android y producción no se modificaron en este ajuste.

## Códigos de barras al consultar y editar productos

La ficha nueva «Ver producto», protegida por `products.view`, muestra fotos, variantes y sus códigos de barras sin exigir permiso de edición. El nombre y el botón de cada tarjeta abren esta ficha; «Editar» conserva su permiso anterior. El costo sigue limitado a `products.view_purchase_price`.

La ficha y la edición comparten `BarcodePreview.vue`: barras negras sobre blanco, SKU legible y márgenes de lectura. Se genera geometría Code 128 con Picqer en el servidor, sin crear códigos ni trabajos de impresión al consultar. Flutter utiliza la misma geometría en detalle y edición. El endpoint nativo de etiquetas reutiliza las filas y filtros del controlador web existente, sin habilitar un navegador dentro de Android.

Comprobación: 23 pruebas de API/etiquetas y 318 aserciones correctas, incluidas igualdad de filas web/Android, filtros de fecha, permisos de consulta frente a edición, costos ocultos y ausencia de cambios en ventas o stock. Vite/PWA compilado y revisión con datos sintéticos en consulta, edición y tabla de etiquetas. Solo se actualizaron los entornos locales; VPS y producción siguen pendientes.

## Alta y edición con variantes separadas y fotos del catálogo

Ambas pantallas utilizan `ProductEditor.vue`: datos generales, tarjetas por variante (color, talla, existencias, precios, fotos y código de barras) y galería. Se pueden subir fotos desde una variante o como generales; cada foto admite una o varias variantes. La asociación, el orden de las fotos existentes y los datos se guardan juntos. Se conservan publicación, estilos, permisos de costos, borrado manual y controles de impresión de etiquetas.

Reglas de la galería pública:

- Una foto general se muestra mientras el producto tenga alguna variante disponible.
- Una foto vinculada a una variante se oculta cuando esa variante queda sin existencias o se archiva.
- Una foto compartida se mantiene mientras al menos una de sus variantes esté activa y tenga existencias.
- La última unidad agotada oculta la foto; una venta parcial no la retira si queda stock. Al reponer existencias, reaparece con su orden original.
- El filtro se aplica al listado y al detalle públicos. Administración conserva las fotos: ocultarlas no elimina archivos ni derivados.

La migración aditiva `2026_10_30_000001_add_product_image_variant_ids.php` añade `product_images.variant_ids` nullable. Las asociaciones anteriores siguen leyéndose desde `product_variant_id`; el primer ID se conserva para clientes Android existentes. No se habilitó ni modificó el borrado automático a los 15 días.

Las fotos nuevas usan claves estables de formulario para enlazarlas incluso antes de crear las variantes. Se valida que fotos y variantes pertenezcan al producto, que las asociaciones coincidan con los archivos y que no se mezclen «general» y variantes concretas. Las existencias originales se comprueban al guardar para evitar reponer stock desde un formulario abierto antes de una venta.

Verificación: **171 pruebas PHP correctas, 3 omitidas, 1,761 aserciones**; 36 pruebas específicas correctas y **17 de JavaScript**. Vite/PWA compilado. En navegador se creó un producto sintético con dos variantes y tres fotos, incluidas dos asociaciones en la foto compartida. Al cambiar rojo de 2 a 0, el catálogo pasó de 3 fotos a 2: roja oculta, azul y compartida visibles. Al reponerlo, volvió a 3 sin subir archivos. Revisión móvil a 390 px, con 386 px útiles por la barra de desplazamiento y sin desborde horizontal. La página con iframe se usó solo como herramienta temporal de prueba de la web y se retiró.

Se retiraron el producto, colores y nueve objetos de imagen creados exclusivamente para esta prueba; los códigos asignados quedaron reservados para impedir reutilizarlos. La conciliación de Android QA sigue con once comprobaciones correctas, cuatro ventas, dos apartados, tres abonos y existencias `[16, 18, 1]`. Se restauró la configuración de sesiones y se eliminó el acceso temporal.

Aplicado solo a la web local y al entorno QA, con respaldo previo de código y recursos en el contenedor local. No se modificó el APK, VPS ni producción. Evidencia y verificación: `docs/evidence/product-variant-photos/`.

## Android: variantes y fotos del producto

Se trasladó el editor a Flutter nativo, sin WebView: datos generales, tarjetas separadas por variante con existencias/precios/costos según permiso, código de barras de cada variante y galería con fotos generales o asignadas a una o varias variantes. Los campos se acomodan al ancho disponible y las variantes se apilan en vertical. Se conservan los accesos de importación, etiquetas, detalle y administración de fotos.

`product_draft.dart` mantiene claves estables para las variantes nuevas y adapta los borradores anteriores sin sustituir su captura. Cada archivo se copia al directorio privado de la app y guarda sus asociaciones junto al borrador, incluyendo el contexto para recuperar el selector de Android después de perder la actividad. Las altas, ediciones, cargas adicionales y asociaciones siguen usando el registro cifrado de operaciones y sus claves idempotentes. Se conservan los precios heredados y la comprobación de existencias originales. Un formulario abierto no puede enviarse después de cambiar de cuenta o servidor.

La carga adicional del servidor admite las mismas asociaciones múltiples del editor mediante `StoreProductPhotosRequest`; conserva la compatibilidad con las peticiones anteriores. El administrador de fotos nativo permite elegir General o varias variantes y verifica la fecha del producto al guardar la asociación. No se habilitó el borrado automático de fotos.

Se reprodujo y corrigió un fallo de Atrás de Android en los navegadores internos de los módulos: `NativeBranchContainer` comunica la navegación del módulo activo al sistema, mantiene el historial de los demás y regresa una pantalla sin salir al launcher. La prueba de regresión fallaba antes y pasa después; también se comprobó físicamente desde Fotos y Editar.

Validación de esta etapa: **84 pruebas Flutter correctas**, análisis sin errores ni advertencias (242 recomendaciones de estilo), APK PERF compilado. Servidor: **174 pruebas correctas y 3 omitidas, 1,784 aserciones**. Se instaló actualizando `Samy Boutique PERF`, conservando sesión y el carrito existente de un artículo, contra Android QA local en el puerto 18082.

En Galaxy Tab A9+ se creó exclusivamente el producto sintético `QA Fotos App 20261030`: dos variantes y fotos roja/compartida seleccionadas desde una carpeta propia. El borrador sobrevivió al cierre de la app y a una actualización de APK. Se guardó sin conexión, se reinició la app y se recuperó el pendiente una sola vez, conservando archivos y asociaciones. Al poner rojo en cero desde el editor, el catálogo dejó de mostrar su foto y mantuvo la compartida por azul. También se cargó otra foto desde el administrador, se cambió a General y al reponer rojo reapareció la foto original sin volver a subirla. Se revisó la distribución en horizontal y vertical, y al terminar no había operaciones pendientes.

Se retiraron producto 4, los dos colores creados para esta prueba y nueve objetos de imagen; se conservaron los códigos reservados y los comprobantes de operación para auditoría. Se retiraron los tres archivos de entrada de la tablet. La conciliación final vuelve a tener once comprobaciones correctas, cuatro ventas, dos apartados, tres abonos y existencias `[16, 18, 1]`; los comprobantes idempotentes pasan de 15 a 20 por las cinco operaciones de esta prueba. La rotación original de la tablet quedó restaurada. Cámara, lector y salida física Bluetooth no se probaron en esta etapa; el selector de galería y la recuperación de sus archivos sí se comprobaron físicamente, y la recuperación de actividad está cubierta por pruebas automatizadas.

APK: `samy-boutique-app/artifacts/Samy-Boutique-Productos-Variantes-2026-10-30.apk`, SHA-256 `92be9ef2a3a0b923ed42c2f68cd5b9720c0d5631570cbd5733ea3dc0c45890b2`. Evidencia en `samy-boutique-app/docs/evidence/product-variant-photos/`. Solo se actualizó el entorno local; no se modificó ni desplegó VPS o producción. Los cambios locales previos se conservaron y no se crearon commits.

## Android: Fotos en tabla con el flujo de la web

Se sustituyó la lista de accesos a fotografías por una tabla nativa en `bulk_photos_screen.dart`, exportada desde `products_screen.dart`. Tiene las cinco columnas de `Products/Bulk.vue`: código/producto, categoría/variantes, fotos actuales, agregar fotos y guardar. El código y el nombre quedan fijos durante el desplazamiento horizontal. Incluye miniaturas, archivos pendientes y asociación por foto en la misma fila, quitar archivos, guardar una fila o todas las filas pendientes, estado y errores por fila, y acceso a editar el producto. Mantiene la opción de compartir fotos entre variantes; al cancelar esa selección se conservan tanto las asociaciones como el texto del selector.

El acceso sin selección consulta el catálogo completo, con búsqueda, fecha de alta y paginación del mismo endpoint que la web; antes solo enviaba los IDs de los productos visibles. Con selección conserva los IDs indicados y omite los filtros generales, igual que la web.

`NativeFormController` permite guardar secuencialmente las filas usando el registro idempotente existente. No se crean peticiones paralelas: una respuesta incierta detiene el lote y conserva las filas siguientes para recuperar el pendiente original; un rechazo de validación permite continuar con las demás. Cada fila comparte el borrador de su producto con la carga individual, incluidas copias privadas de los archivos y recuperación del selector de Android. Tras guardar utiliza la nueva fecha del producto para la siguiente carga, sin volver a enviar los datos comerciales del formulario general. Una fila desactualizada puede recargar los datos para revisión antes de reintentar.

La suite completa pasa con **95 pruebas Flutter** y el análisis no tiene errores ni advertencias. En Galaxy Tab A9+ se seleccionaron fotos sintéticas para productos nuevos 5 y 6, con una asociación general y otra a la variante 8. Los dos borradores sobrevivieron al cierre y actualización de la APK. «Guardar todas» agregó una foto a cada producto; después «Guardar fotos» agregó otra foto solo a producto 6 usando su versión actualizada, sin salir de la tabla. Las miniaturas se actualizaron en sus filas. Se comprobó en vertical que el código conserva exactamente sus coordenadas mientras se desplazan las demás columnas.

Se retiraron ambos productos de prueba y nueve objetos de imagen; se conservaron códigos y comprobantes idempotentes. La conciliación final mantiene once comprobaciones correctas, cuatro ventas, dos apartados, tres abonos, existencias `[16, 18, 1]` y 23 comprobantes (tres cargas nuevas). El POS conserva su carrito de un artículo y dos productos de catálogo. Evidencia: `samy-boutique-app/docs/evidence/bulk-photo-table/`. No se modificó la web funcional, VPS ni producción; solo Android y sus pruebas/documentación locales.

La APK final también se verificó en la tablet al cancelar «Varias variantes»: el selector volvió a «Todas las variantes» y se retiró el archivo temporal sin enviarlo al servidor. Se dejó abierta la tabla con los dos productos originales y sin fotos pendientes. APK instalado: `samy-boutique-app/artifacts/Samy-Boutique-Fotos-en-Tabla-2026-10-30.apk`, SHA-256 `f66431c668efb0ebf689d10868e03a7f55110b2fc96dfa9f4648bc1ee0df81df`.

## Cierre local Android — 30 de octubre de 2026

El usuario confirmó cobros con conexión; sin red se conservan carritos,
borradores, adjuntos y pendientes, sin dar por cobrado un abono o una venta.
Se cerraron en la tablet cámara y recuperación de su foto al reiniciar, Firebase
con proceso ausente y apertura del recurso, descuento/cupón, devolución de venta,
ajuste y devolución de apartado liquidado, entrega, abono de cajero offline con
recuperación única, cancelación con devolución de anticipo y corte guardado.

Correcciones: mínimo del descuento global POS a tres prendas como la web,
valores cero requeridos en descuentos, mensajes específicos de validación, área
segura de formularios, fechas del corte con zona y recarga al generar la misma
fecha. Los apartados mantienen su política de dos prendas. La conversión de
horas corregida también se aplica a web local; sin despliegue al VPS.

Flutter: 101 pruebas aprobadas, cero errores/advertencias y 260 avisos
informativos; APK PERF release compilada. Laravel: 176 aprobadas de 177, 1,842
aserciones, solo omitida R2 real; benchmarks opcionales ejecutados sin red.
Conciliación de 21 comprobaciones y las once originales correctas. Se conservan
las operaciones QA nuevas para pruebas físicas futuras y todos los registros
anteriores, incluida la variante 10 y precios modificados por el usuario.

Detalle vigente: `samy-boutique-app/docs/TABLET_ACCEPTANCE_2026-10-29.md` y
`docs/evidence/local-stage-close/verification.json` dentro de Android. Restan
lector/impresoras físicos y fase VPS/infraestructura; publicación al final. El
borrado de fotos a 15 días continúa deshabilitado.
## Etiquetas: validación física del 31 de octubre

Android se actualizó para codificar Code128 B/C en 40 × 30 mm sin truncar SKU ni
reducir módulos/márgenes. Impresora Bluetooth real: etiqueta completa y alineada,
reimpresión idéntica y recuperación manual única tras apagar/encender, todo
confirmado por el usuario. El pendiente conservó su estado al reiniciar y no se
reenvió automáticamente. 103 pruebas Flutter aprobadas; base QA, stock y carrito
anteriores intactos. Solo se imprimieron datos sintéticos. Evidencia y límites en
`samy-boutique-app/docs/evidence/label-printer-2026-10-31/verification.json` y la
sección del 31 de octubre de `ETIQUETAS_BLUETOOTH_2026-10-24.md` en Android.
Lector físico, impresora de tickets y VPS siguen pendientes; producción intacta.