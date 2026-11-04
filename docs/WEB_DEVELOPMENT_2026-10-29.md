# Desarrollo web · 29 de octubre de 2026

## Correo Resend

Se utiliza la cuenta `digitalex.mx` que el usuario seleccionó en Chrome. La primera prueba se hizo con `digitalex.com.mx`; después el usuario compró y eligió **samyboutique.com.mx**. Este dominio ya está verificado y tiene su propia clave de desarrollo con **Sending access** restringido a él. Las claves de los otros sistemas no se modificaron. Ver `DOMAIN_SAMYBOUTIQUE_COM_MX.md` para el estado vigente y los registros DNS.

La credencial vigente se guarda fuera del repositorio, en `C:\Users\Angel\.codex\private-configs\samy-resend-development.env`. No debe copiarse al código, imágenes Docker, documentación o mensajes. El remitente vigente de desarrollo es `notificaciones@samyboutique.com.mx`.

Primera prueba real: `QA-WEB-20261029-214558`, aceptada por SMTP, estado **Delivered** en Resend y mensaje encontrado en la bandeja de entrada de `angelescalante0501@gmail.com`. ID Resend: `01a189a0-7f89-7a01-82ad-2dd0583f5bdf`. La segunda prueba autorizada, `QA-WEB-20261029-220026`, confirmó también recepción en INBOX desde el nuevo remitente `notificaciones@samyboutique.com.mx`. Los intentos previos rechazados no entregaron correo.

El primer intento usó una cuenta distinta del navegador integrado, sin dominio verificado, y fue rechazado por la restricción del remitente de pruebas. Esa clave no es la usada por Samy. El SMTP anterior de Neubox rechazó la autenticación. No se cambió su contraseña ni la configuración de producción.

### Entorno local conectado

El archivo opcional `compose.resend-development.yaml` conecta únicamente los servicios locales de Samy al SMTP de Resend. En `APP_ENV=local`, `MAIL_DEVELOPMENT_TO` redirige **todos** los destinatarios, incluidas copias y copias ocultas, al Gmail de prueba. Se comprobó la redirección con el transporte real de Laravel en memoria y red deshabilitada, sin enviar otro mensaje externo.

Arranque de la configuración local seleccionada, desde PowerShell en la raíz del repositorio:

```powershell
docker compose --env-file .env --env-file docker/local/compose.env --env-file C:/Users/Angel/.codex/private-configs/samy-resend-development.env -f compose.local.yaml -f compose.resend-development.yaml up -d --no-build web queue-default queue-sales scheduler
```

Sin el archivo opcional se conserva Mailpit. Antes de activar Resend se comprobó que no existían correos pendientes: los 18 trabajos existentes pertenecían exclusivamente a la cola push, cuyo transporte sigue deshabilitado. La venta sintética de la base de aceptación se procesó primero con Mailpit: `Nueva venta registrada: VE-00001`, destinatario `qa-web@samy.test`.

La publicación en VPS está expresamente pospuesta por el usuario. No se modificó el VPS ni datos de producción. Los registros DNS de correo de `samyboutique.com.mx` sí se añadieron con la autorización expresa documentada en el registro del dominio.

## Correcciones verificadas

- Los comprobantes web de venta, apartado y abono abren una página privada imprimible de 58/80 mm y permiten guardar PDF desde el navegador. El cobro automático conserva un enlace al comprobante y no informa que falta Android. La impresión nativa conserva su ruta.
- Los comprobantes respetan descuentos, pagos netos, devoluciones y saldo actual. El comprobante de abono distingue el pago original del estado actual del apartado. Se verificaron permisos, relación pago/apartado, fechas posteriores al día 12, escape HTML y ausencia de nuevas operaciones al consultar un comprobante.
- El alta de apartados permite elegir entre variantes disponibles, conservando talla, color y precio; ya no elige siempre la primera variante.
- La política de caché admite las fotos públicas sintéticas y MinIO HTTP exclusivamente en los hosts loopback configurados. Conserva el rechazo de URLs privadas, firmadas o ajenas.
- Symfony Mime se actualizó de 7.4.19 a 7.4.22; Composer no informó vulnerabilidades tras el cambio.

Verificación automatizada en imagen PHP 8.2: **147 pruebas correctas, 1,433 aserciones, 3 omitidas** (benchmark y prueba externa condicionados). Pruebas JavaScript específicas de impresión y caché: **11 correctas**. Build Vite/PWA correcto. El primer lanzamiento de la imagen de pruebas no incluía APP_KEY; se corrigió el entorno de prueba y se repitió la suite correctamente. La imagen de pruebas incluye ahora una clave local fija para evitar esa omisión.

## Alcance pendiente

Este registro no declara terminada la aceptación manual en móvil ni la recepción push en el dispositivo. La impresión física/Bluetooth y la conciliación offline se verifican con Android. El borrado automático de fotos tras 15 días permanece pospuesto. La base de aceptación `samy_web_acceptance_20261029` contiene solo datos sintéticos y está separada de `samy_local` y del respaldo `samy_restore`.

## Revisión de cierre posterior · 29 de octubre

Se revisó el login, dashboard y navegación de gerente en un viewport real de 390 × 844 del navegador integrado. Login y dashboard no desbordaron horizontalmente (375 px de contenido, con la barra vertical). El gerente accedió al listado y detalle de apartados. Las pruebas se realizaron en la base sintética separada, conservando una venta de $990 y un apartado de $495 con abono de $400 y saldo de $95.

La revisión encontró y corrigió:

- El dashboard calculaba $150 pendientes para ese apartado porque ignoraba descuentos y devoluciones. Ahora resumen y recientes usan el saldo neto; los excedentes de un apartado no reducen la deuda de otro. La actualización visible confirmó $95. Una prueba de regresión comprueba descuentos manuales, fidelidad, devoluciones, excedentes y exclusión de cancelados.
- El listado de apartados mostraba el subtotal como «Total» y el pago bruto como «Abonado». Ahora muestra total después de descuentos y pago aplicado neto en escritorio y móvil.
- La tabla de historial de abonos salía del ancho del teléfono. Se añadió un contenedor desplazable y se permitió que la columna de la cuadrícula se reduzca. Se compiló correctamente; la revisión visual posterior quedó interrumpida.
- La activación web podía declarar vinculado un navegador con permiso concedido aunque FCM no hubiera devuelto un token. Ahora exige token y registro en el servidor; en caso contrario muestra que la vinculación está pendiente. Se probaron token válido, error de FCM, token vacío y permiso denegado sin envíos externos.

Validación de esta revisión: **15 pruebas PHP correctas, 129 aserciones** (saldo del dashboard, flujos y permisos de ambos roles, bandeja privada, dispositivo cifrado, baja al cerrar sesión, preferencias, deduplicación y reintentos de entrega). **14 pruebas JavaScript correctas**, incluyendo registro push, comprobantes y caché. Build Vite/PWA correcto. No se repitió la suite completa previamente aprobada porque los cambios de esta revisión se validaron con las pruebas específicas afectadas.

### Interrupción de la validación visual y push

La herramienta de control del navegador detuvo el acceso al intentar revisar el permiso nativo de Chrome: no pudo determinar con suficiente seguridad la URL actual de Windows. No se intentó eludir ese bloqueo. La base de aceptación confirma **0 dispositivos web activos con token**; por tanto, no se envió una prueba FCM ni se afirma recepción en el equipo.

En ese punto quedaron pendientes la revisión visual restante y la recepción push real. Se retomaron y resolvieron en la revisión siguiente.

## Cierre de revisión móvil y recepción push real

Se retomó el entorno de aceptación existente sin eliminar contenedores ni bases. Se actualizaron sus archivos desde la imagen local compilada. No se modificaron datos comerciales de producción.

Revisión visual en viewport **390 × 844**:

| Pantalla | Comprobación | Resultado |
| --- | --- | --- |
| Dashboard | Resumen, acciones y saldo neto del apartado | $95 pendientes; sin desborde horizontal |
| Apartado | Detalle, prendas, saldos y contenedor del historial de abonos | Ancho del documento igual al viewport; tabla desplazable dentro de su región |
| Alta de apartado | Productos, variantes, cliente, vigencia y abono | Controles apilados y accesibles; selección L/Negro y alta ya comprobadas con cajero |
| POS | Catálogo, lector, selección de variante y apertura del carrito | Modal y carrito caben en pantalla; producto de $200 correctamente agregado sin registrar otra venta |
| Productos | Listado y formulario de alta, con ocasiones múltiples | Sin desborde; fotos, variantes y estilos visibles |
| Clientes | Listado y formulario de alta | Sin desborde; nombre, teléfono, correo y guardar accesibles |
| Reportes | Periodo, descargas, pestañas y resumen | Sin desborde; $990 de venta sintética coincidente |
| Catálogo público | Logo, menú móvil, banner alto y filtro de estilo | Sin desborde; «Bodas» muestra solo Vestido midi marfil y deja visible «Estilo: Bodas» con opción de quitarlo |

Las capturas se guardaron en `C:\Users\Angel\.codex\.tmp\samy-final-*-movil.png` (dashboard, apartado, POS, carrito, producto, cliente, reportes, catálogo y filtros).

### Push real comprobada

El usuario aceptó manualmente el permiso de Chrome después de que la política del navegador impidiera automatizar sus ajustes internos. El dispositivo web de `cajero@aceptacion.samy.test` quedó registrado con permiso **granted** y token cifrado en la base de aceptación.

Se preparó exclusivamente ese destinatario y se ejecutó `ops/check-web-push-delivery.php` en un proceso aislado **sin conexión a la base de datos**, con Firebase `samy-boutique-dev` y credenciales de solo lectura. El envío usa el servicio real `FirebaseMessagingService`. El usuario del proceso coincidió con el propietario del archivo de credenciales; no se ampliaron permisos del archivo. Los cachés de arranque se dirigieron al directorio temporal del proceso.

- Notificación de aceptación: ID **3**, categoría administrative.
- Título: **Samy Boutique · Prueba web completada**.
- Firebase aceptó el mensaje, sin error.
- Chrome registró **«Mensaje FCM recibido en primer plano»** a las **16:35:42, hora de Ciudad de México**, y actualizó su bandeja con el aviso de prueba.
- Evidencia: `C:\Users\Angel\.codex\.tmp\samy-final-push-recibida.png`.
- Se retiró el token de los archivos temporales de preparación tras la prueba; las credenciales privadas nunca se imprimieron.

La comprobación demuestra recepción FCM en el cliente web y aparición en su bandeja. No se declara comprobado el aspecto visual de un aviso nativo del sistema operativo ni impresión Bluetooth. El transporte de push del entorno comercial/local principal no se activó globalmente: la prueba fue acotada a la base y al dispositivo de aceptación. La configuración persistente de envío del servidor se establece por entorno al publicar.

Durante esta revisión también se corrigió el título de nuevas notificaciones de apartado para mostrar el total descontado y el abono neto. No se reescribieron avisos históricos. Regresión específica: **14 pruebas PHP correctas, 63 aserciones** (saldo/mensaje de apartado, cola de avisos y sistema de notificaciones). Build local correcto. Las pruebas previas de roles y las 14 de JavaScript siguen documentadas arriba.

**Los tres puntos de esta revisión quedan cerrados:** pantallas móviles indicadas, permisos de gerente/cajero y recepción push real. Continúan fuera de esta fase impresión/Bluetooth, conciliación sin conexión de Android y publicación del VPS. El borrado automático de fotos tras 15 días sigue pospuesto.

## Actualización local del 30 de octubre — cierre Android

La aceptación ampliada está documentada en el repositorio Android,
`docs/TABLET_ACCEPTANCE_2026-10-29.md`, sección «Cierre ampliado local», y
`docs/evidence/local-stage-close/`. La política confirmada por el usuario es
cobrar con conexión y conservar preparación/pendientes sin red.

Durante la prueba real se corrigieron timestamps del corte en
`SalesAnalyticsService` y `CashCutsController`: ahora incluyen zona mediante ISO
8601 en ventas, cancelaciones y fechas de guardado/detalle. Los formateadores web
existentes aceptan ese formato y Android muestra el mismo instante local que el
detalle de venta. Una regresión cruza movimientos, detalle, resumen, historial y
detalle de corte, incluyendo una hora que cambia de día en Ciudad de México.
Los PHP se actualizaron solo en web local 18080, con copia de respaldo. Sin
cambios al historial de cortes ni despliegue al VPS/producción.

Suite aislada final: 176 aprobadas de 177, 1,842 aserciones; los dos benchmarks
opcionales se ejecutaron con red bloqueada. Solo se omite la prueba externa R2,
correspondiente a infraestructura. El verificador de la tablet conserva las once
comprobaciones iniciales permitiendo nuevas variantes sintéticas; la conciliación
ampliada compara además los registros originales completos contra un respaldo.
## Búsqueda mediante lector por SKU de variante — 31 de octubre de 2026

Una prueba física en Android recibió QAT-000015 correctamente, pero Productos
solo buscaba SKU del padre, nombre y descripción. `Product::scopeSearch` ahora
incluye `variants.sku` con `orWhereHas` dentro del mismo grupo, conservando los
filtros externos. Web y la API de pantallas nativas comparten la corrección.
`ProductSearchScannerTest` cubre coincidencia solo en variante, un único padre
con varias coincidencias, filtros de estado/género/categoría y ninguna venta o
cambio de stock. Suite actual completa: 175 pruebas aprobadas, 3 omitidas y 1822
aserciones, SQLite en memoria, sin red y zona de aplicación UTC. No se desplegó,
modificó producción ni conectó samy_restore. El resultado físico y APK están en
`../samy-boutique-app/docs/evidence/scanner-physical-2026-10-31/`.

La repetición física en la APK actualizada confirmó un solo producto al leer QAT-000015 después de reconectar el lector Bluetooth. El código padre QAT-000014 es distinto; quedó validada la búsqueda por variante.


## Aceptación privada VPS — 1 de noviembre de 2026

Pruebas privadas de web y Android completadas con datos sintéticos, sin cambios a producción ni DNS. Resultado, conciliación y límites en [VPS_PRIVATE_ACCEPTANCE_2026-11-01.md](VPS_PRIVATE_ACCEPTANCE_2026-11-01.md).
