# Corte de producción — 2026-11-01

El usuario autorizó expresamente el corte con cajas sin pendientes. Publicación realizada en el VPS 2.25.126.224.

- Catálogo: https://samyboutique.com.mx (www redirige al dominio principal).
- Sistema: https://sistema.samyboutique.com.mx; Android usa /api por HTTPS.
- Cloudflare: los tres registros web pasan por proxy y SSL está en Completo (estricto). Certificados de origen válidos para los tres nombres.
- Samy de Neubox sigue en mantenimiento HTTP 503; su única tarea de notificaciones quedó pausada. Configuración anterior conservada para recuperación. No reactivar como segunda caja.
- Cuenta FTPS temporal retirada; se confirmó rechazo de inicio de sesión. Respaldos conservados fuera del directorio público, en carpeta 0700.

## Conciliación y recuperación

Copia SQL final: SHA-256 `a342a0a8d31d88ea964e41a934f45b96575d2b69191c625eda6af94a2fd32c05` (176009 bytes).

Archivo final: SHA-256 `9c8f8c7756c51972bbef4ce3ee79dcb75683b2f4d3fb3a003ab99b2e80ea39a9` (1067439238 bytes). Copias local y VPS coinciden.

Restauración en el proyecto samy-production, esquema samy_production. Se convirtieron diez tablas MyISAM a InnoDB y las migraciones se ejecutaron dos veces. La auditoría de 42 tablas y 151 columnas numéricas no encontró cambios en filas, sumas ni valores originales. Ninguna aplicación o worker usa samy_restore.

Se copiaron y verificaron 790 fotos activas; se generaron 1580 derivados WebP con orientación EXIF correcta. Conciliación de 41 tablas originales tras la copia: sin diferencias inesperadas. Tras preparar el runtime: 34 tablas de negocio sin diferencias; las sesiones anónimas y los cambios técnicos de notificaciones se excluyen de esta segunda comparación.

Los 123 trabajos históricos se conservaron en legacy-cutover-20261101 sin enviarse. Dispositivos y tokens anteriores desactivados/expirados. El escáner de vencimientos solo incluye nuevos apartados posteriores al ID 33. Workers de aplicación, push y scheduler iniciados.

Primer respaldo Restic de producción: `2530a535f1b338e2859d5aa62cbaf3718c8c8b85e2a7269d6d8ff27033cc02e5`, terminado el 1 de noviembre a las 18:16, hora de México. Cifrado en R2 privado; check --read-data y restauración de 3979 archivos con hashes verificados. SQL restaurado en samy_backup_verify_production_20261101: 58 tablas y 8052 filas comparadas, ninguna diferencia; sin app ni workers conectados a esa copia.

Timer de respaldo nocturno y recuperación de capturas interrumpidas habilitados. El borrado automático de fotos sigue desactivado.

## Comprobaciones reales del dominio

- Chrome: catálogo y login de gerente, con 13 de 13 imágenes visibles cargadas. Sesión abierta por el usuario en el dominio definitivo.
- Tablet Galaxy Tab A9+: APK nativa de producción mx.com.samyboutique.app, versión 1.0.0 / 2026110101. Sesión iniciada por el usuario; dashboard y bandeja recibidos desde la API definitiva.
- Android: permiso de notificaciones concedido mediante el diálogo del sistema; la app muestra «Notificaciones vinculadas».
- La prueba final de aviso de producción aún no se envió. Al aceptar el permiso web, el control de computadora se detuvo porque no pudo verificar la URL actual. Queda pendiente confirmar permiso/token web, enviar un único aviso administrativo a la cuenta actual y comprobar recepción/tap en web y Android.
- La impresora de tickets del cliente sigue pendiente de prueba física. Las simulaciones 58/80 mm y las pruebas físicas de etiquetas y lector ya están documentadas en la preparación.

No se generaron ventas, abonos ni otros movimientos de prueba con los datos comerciales. Los cambios locales se conservaron sin commit. No se modificaron otros proyectos del VPS.

## Recuperación después de abrir operaciones

El VPS es la autoridad de datos. Si ya existen escrituras nuevas, no basta regresar DNS ni levantar Neubox: poner ambos en mantenimiento, respaldar el destino y conciliar para conservar cobros y operaciones nuevos. Las claves, SQL y archivos privados están fuera del repositorio.
## Carrusel recuperado después de publicar — 1 de noviembre

La copia comercial de Neubox no tenía catalog_slides. Los dos banners que se veían en el catálogo local estaban en samy_local y MinIO; las imágenes originales siguen en resources/demo-showcase. Se trasladó únicamente ese contenido visual, sin ejecutar BoutiqueShowcaseSeeder ni crear productos, categorías o movimientos de demostración.

Se comprobaron por SHA-256 las cuatro imágenes contra las que servía el catálogo local, se copiaron a R2 público y se releyeron con bytes/hash idénticos antes de crear las dos diapositivas (IDs 1 y 2). Versiones de escritorio: 1800 × 600. Versiones móviles originales: 900 × 1124.

Destinos adaptados al catálogo real:

- Colección que inspira → /catalogo?q=vestido (203 resultados al verificar).
- Tu estilo, todos los días → /catalogo?category_id=5 (12 conjuntos al verificar). No se reutilizaron los IDs de las categorías de desarrollo.

Comprobado en Chrome sobre el dominio público: carrusel visible, avance automático entre ambas diapositivas, imagen de escritorio cargada por HTTPS y selección de imagen móvil en viewport 390 × 844, sin desborde horizontal (386 px de documento). Se restauró el tamaño normal del navegador y se dejó el catálogo público abierto. Los destinos devuelven sus filtros y productos correspondientes.

El cambio fue de contenido/configuración del carrusel; no requirió recompilar o reemplazar la aplicación. Evidencia: docs/evidence/release-2026-11-01/carousel-recovery.json y carousel-production-desktop.png. El resto de pendientes de impresora y confirmación final de push sigue como se documentó arriba.
## Recepción real de avisos de producción — 1 de noviembre

Chrome y Android quedaron con permiso granted y token registrado (dispositivos 5 y 6, cuenta gerente). Se emitió un solo aviso administrativo «Samy Boutique · Avisos de producción»: anuncio 2, notificación 52, clave idempotente 26310101-1101-4000-8000-000000000001. Entregas 94 y 95 con estado sent y sin error. El usuario confirmó recepción en ambos dispositivos. La confirmación de navegación al tocar el aviso está en curso.

Se detectó que el rol gerente de la base heredada carecía de notifications.send, definido para ese rol en RolesAndPermissionsSeeder. Se añadió exclusivamente ese permiso, conservando los demás y una copia de la asignación anterior; se vació el caché de permisos. Comprobado: gerente puede enviar; cajero no. No se ejecutó el seeder completo ni se crearon movimientos comerciales.
### Cierre de notificaciones de producción

El usuario confirmó que al tocar «Samy Boutique · Avisos de producción» tanto en Chrome como desde la cortina de Android se abre el Centro de avisos mostrando el aviso correcto. Quedan comprobados permisos, registro, entrega real y navegación en ambos clientes. No se reenvió el aviso ni se generaron movimientos comerciales. Único pendiente de aceptación de esta etapa: prueba física de impresión y reimpresión con la impresora de tickets del cliente.

## Descarga directa de Android

APK de producción 1.0.0 / 2026110101 publicada por solicitud del usuario en https://imagenes.samyboutique.com.mx/samy/android/releases/1.0.0-2026110101/Samy-Boutique-1.0.0.apk . Se verificaron SHA-256 y 66078350 bytes mediante relectura completa en R2; HTTPS público responde 200 con MIME Android y Content-Disposition attachment. No se publicó el AAB ni material de firma privado.

## Conciliación de SKU de variantes heredadas

El usuario detectó códigos vacíos al imprimir etiquetas en web y Android. Los 527 productos conservaban SKU, pero las 945 variantes heredadas tenían sku nulo/vacío y las 13 categorías no tenían sku_prefix. Sin conflictos en catalog_code_conflicts.

Se guardó copia completa previa de productos, variantes y categorías en el VPS, fuera del repositorio, private-audit/legacy-variant-code-repair. Se configuraron prefijos únicos VL, SH, BL, PV, CJ, VC, FA, BM, PM, SP, VN, PA y AJ según las categorías existentes. Se ejecutó catalog:fill-missing-codes --apply, que emite y reserva códigos únicamente donde faltan. Se actualizaron timestamps de los 527 padres para propagar los nuevos códigos a clientes incrementales Android.

Resultado: 945 códigos de variante emitidos; cero SKU vacíos y cero duplicados entre productos y variantes. Los SKU originales de los padres se conservaron. Comparación completa previa/posterior de 527 productos y 945 variantes, excluyendo exclusivamente los nuevos SKU y timestamps: ninguna otra diferencia, incluyendo stock, precios, talla, color y estado. Nueva ejecución en modo inventario: cero faltantes en ambos tipos. La copia previa y el informe permanecen fuera del repositorio. No se imprimió ni creó una operación comercial de prueba.
## Auditoría integral posterior a la migración — 1 de noviembre

Se cotejaron 32 tablas originales de la copia inicial verificada contra producción, excluyendo cambios técnicos deliberados de almacenamiento, SKU y timestamps. Ninguna fila comercial original eliminada ni modificada inesperadamente: clientes, ventas, pagos, apartados, abonos, cortes, inventario, precios y los cinco ajustes conservados. Las adiciones observadas corresponden al caché, al aviso de producción y al permiso notifications.send ya documentados.

Comprobación de estructura y configuración: cero migraciones pendientes; matrices completas de gerente (26 permisos) y cajero (13) iguales al seeder; cero variantes huérfanas, códigos vacíos/conflictivos, tallas/colores/categorías faltantes, stock negativo o productos sin precio. No se ejecutaron seeders de demostración. La ausencia de ocasiones comerciales en la fuente no se rellenó con asociaciones de desarrollo.

Se revisaron las 2375 referencias públicas de originales, derivados, banners y logo, con comprobación de existencia y tamaño. Se encontró y corrigió un carácter duplicado en la ruta configurada del logo, conservando el entorno anterior fuera del repositorio. Tras la corrección: cero objetos faltantes o tamaños distintos, y logo público HTTPS 200. Se recargaron únicamente app y sus workers/scheduler; los demás sistemas del VPS se conservaron.

Android: comprobación de los controladores de API en producción, sin generar movimientos: 527 productos, 945 variantes, cero errores de campos; 945 vistas previas de código de barras válidas. La APK distribuida mantiene SHA-256 verificado, API del dominio de producción, Firebase de producción y HTTPS obligatorio. Este ajuste de datos no requiere nueva APK; clientes existentes deben actualizar la consulta/sincronización.

Dominios público/sistema, zona horaria, cookies seguras, discos R2 públicos/privados, remitente, Firebase y colas revisados. Sin redirección de correo de desarrollo ni trabajos heredados en colas activas. El borrado automático de fotos continúa desactivado; app/workers usan samy_production. Catálogo y login responden HTTPS 200 tras la revisión.

Se creó un nuevo respaldo cifrado externo del estado corregido (3984 archivos) y se verificó lectura integral del repositorio y hashes de la restauración. Los seis servicios de producción quedaron activos y el temporizador de respaldo sigue programado. Informes completos privados y copias previas permanecen fuera del repositorio en el VPS; resumen sin secretos: docs/evidence/release-2026-11-01/migration-completeness-summary.json. No se realizaron cobros ni operaciones comerciales de prueba.

No se encontraron otros faltantes de migración dentro del alcance comprobado. Único pendiente de aceptación previamente acordado: impresión y reimpresión física con la impresora de tickets del cliente.

## Etiquetas masivas: solo existencias disponibles — 1 de noviembre

Por solicitud del usuario, ProductLabelController.index filtra antes de paginar los productos con estado disponible y con alguna variante activa de stock mayor que cero. Las filas incluyen exclusivamente esas variantes; no se ofrecen padres sin variantes, variantes agotadas ni inactivas. Web y Android consumen el mismo controlador; Android no requiere APK nueva y debe recargar la pantalla con conexión. Se conservan las reimpresiones históricas.

Validación: ProductLabelTest, 13 pruebas y 166 aserciones aprobadas en contenedor aislado sin red, SQLite en memoria y datos sintéticos. Incluye paridad de filas/totales web y API Android, variantes agotadas/inactivas y producto sin variantes vendibles. Producción: 314 productos, 421 filas en 11 páginas, cero filas no disponibles; lectura únicamente, sin crear trabajos de impresión ni movimientos comerciales. Pantalla comprobada por HTTPS en Chrome.

La primera revisión r6 referenció erróneamente stock en products; la verificación en MySQL lo detectó y se corrigió de inmediato. Versión final activa: samy-release-app:20261101-r7, usando exclusivamente inventario de variantes. Fuente de recuperación y manifiesto actualizados, copia anterior conservada fuera del repositorio. Gateway y otros sistemas sin cambios de comportamiento. Evidencia: label-availability.json y labels-available-production.png.

## Etapa terminada — 4 de noviembre de 2026

El usuario confirmó que los tickets físicos Android funcionan y que el logo de la nueva APK 1.0.2 salió bien. Con las notificaciones web/Android y su navegación ya aceptadas, queda cerrado el último pendiente de aceptación de esta etapa. No se realizaron nuevas operaciones ni despliegues para este cierre documental.

Consultar [Cierre de etapa web y Android](STAGE_CLOSURE_2026-11-04.md) para el estado vigente, alcance comprobado, APK final y límites de la evidencia física. El borrado automático de fotos a los 15 días continúa pospuesto y desactivado por decisión previa.
