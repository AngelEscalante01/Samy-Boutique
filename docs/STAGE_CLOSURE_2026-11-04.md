# Cierre de etapa — Web y Android

Fecha de cierre: 4 de noviembre de 2026.
Estado: **etapa terminada y aceptada por el usuario**.

El usuario confirmó: «Ya probamos la nueva APK y el logo salió bien» y solicitó documentar lo probado y dar por terminada esta etapa. Esta confirmación cierra el último pendiente de aceptación física: el logo compacto en los tickets Android. No se ejecutaron nuevas operaciones, despliegues ni pruebas comerciales para redactar este cierre.

## Alcance comprobado

| Área | Validación registrada |
| --- | --- |
| Web | Revisión móvil, permisos de gerente/cajero, saldos del dashboard, totales de apartados, comprobantes y desbordes móviles. |
| Android nativo Flutter | Login, permisos y flujos de productos/variantes, clientes, ventas, apartados y abonos revisados; interfaz alineada con la web conservando el POS y funciones nativas. Sin WebView. |
| Conectividad y pendientes | Cobros con conexión por decisión del usuario; conservación de carritos/borradores/pendientes, persistencia y recuperación comprobadas en el alcance de las pruebas locales. No se ofrece cobro offline. |
| Lector físico Bluetooth | Lectura en POS sin tocar el buscador, búsqueda por código de variante en Productos, reconexión y convivencia con teclado manual. Corrección Android de consulta al servidor distribuida desde APK 1.0.1 e incluida en 1.0.2. |
| Etiquetas físicas | Salida completa y alineada; nombre largo con Ñ y precio legible; reimpresión igual; fallo con impresora apagada, sin impresión automática al reconectar y recuperación manual de una sola copia. |
| Tickets | Simulación independiente en 58 y 80 mm; tickets físicos Android aceptados por el usuario. Logo compacto de APK 1.0.2 confirmado en papel el 4 de noviembre. |
| Seguridad de impresión | Pruebas automatizadas de reimpresión idéntica, doble toque, bloqueo de concurrencia, fallo/envío parcial y recuperación manual sin repetir operaciones comerciales. |
| Notificaciones | Recepción real en Chrome y Android en producción; usuario confirmó que tocar el aviso abre el Centro de avisos correcto en ambos. |
| Migración | Conciliación de 32 tablas originales, 527 productos y 945 variantes; corrección de códigos faltantes, sin cambios comerciales inesperados; revisión de 2375 referencias públicas y configuración. |
| Catálogo público | Carrusel recuperado, filtros, imágenes y mapa real de Google Maps bajo el carrusel; revisión de escritorio y móvil. |
| Producción y recuperación | Dominios/HTTPS, API, Firebase, correo, almacenamiento, permisos y colas revisados. Respaldo cifrado externo verificado con lectura y restauración; programación de respaldos registrada. |

Las pruebas automatizadas finales del logo incluyen 22 pruebas Flutter, 10 pruebas del decodificador y 10 comprobantes ESC/POS simulados entre ambos anchos, con análisis dirigido sin incidencias. La confirmación física del cliente no identifica modelo ni ancho del rollo: no se afirma que ambos tamaños se hayan probado físicamente ni que cada escenario automatizado se haya repetido en hardware.

## Entrega final

- Catálogo: https://samyboutique.com.mx
- Sistema y API: https://sistema.samyboutique.com.mx (API en /api).
- APK de producción: [Samy Boutique 1.0.2](https://imagenes.samyboutique.com.mx/samy/android/releases/1.0.2-2026110301/Samy-Boutique-1.0.2.apk).
- Versión interna: 2026110301; paquete mx.com.samyboutique.app; firma de producción conservada.
- SHA-256 APK: 7b1cbd423e28489d77bd86fffffccefeba325b1a831bd75db225ea66e62f7fff.

La aceptación de impresión corresponde a la nueva APK probada por el usuario. No se atribuye instalación verificada a otras tablets o equipos que no se hayan revisado.

## Decisión que permanece pospuesta

El borrado automático de fotos después de 15 días continúa desactivado hasta resolver la conciliación offline de Android. Es una decisión fuera del cierre de esta etapa, no una prueba de impresora pendiente. samy_restore permanece como respaldo y no es base de ejecución de la aplicación ni de workers.

## Evidencia y documentos relacionados

- [Ejecución y auditoría de migración](RELEASE_EXECUTION_2026-11-01.md).
- [Aceptación de impresora del cliente](CLIENT_TICKET_PRINTER_ACCEPTANCE_2026-11-01.md).
- [Mapa del catálogo](CATALOG_MAP_2026-11-03.md).
- [Logo Android y pruebas](../../samy-boutique-app/docs/ANDROID_TICKET_LOGO_2026-11-03.md).
- [Corrección del lector Android](../../samy-boutique-app/docs/ANDROID_SCANNER_FIX_2026-11-01.md).

Los pendientes históricos de esos documentos deben interpretarse según su fecha; este cierre los actualiza en lo relativo a notificaciones e impresión aceptadas. No quedan pruebas funcionales pendientes acordadas para esta etapa. Los cambios locales se mantienen sin commit y las credenciales y respaldos privados quedan fuera de esta documentación.
