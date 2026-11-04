# FCM: automatización DEV y preparación de producción — 10 oct 2026

**Actualización posterior:** la prueba única real de despacho automático DEV pasó. Véase [FCM_AUTO_DEV_E2E_2026-10-10.md](FCM_AUTO_DEV_E2E_2026-10-10.md). El estado DEV bloqueado descrito abajo corresponde al momento anterior a esa prueba; los bloqueos de producción continúan.

## Decisión y estado

Se selecciona **Cron Neubox cada cinco minutos** para la primera salida. Ejecuta el outbox MySQL existente fuera de `POST /api/sales`; no requiere otra cuenta, servidor, endpoint público ni Blaze. La latencia esperada es de 0 a 5 minutos más el tiempo de proceso y reintento; no se anuncia como tiempo real. Para un objetivo de segundos, la alternativa es un worker Laravel `queue:work database --queue=push-notifications` permanentemente supervisado **solo si Neubox confirma que el plan admite procesos persistentes**. Debe conservarse el Cron directo como recuperación. Un procesador externo exigiría hosting 24/7, acceso seguro a la misma cola/base de datos o una API autenticada y monitoreo; no se implementa ahora.

Estados al cierre:

- `AUTOMATIC_DEV_DISPATCH_BLOCKED`: timer DEV activo y verificado, con compuerta `NOTIFICATIONS_PUSH_ENABLED=false`; prueba integral con FCM simulado pasa. Cuatro despachos DEV existentes siguen pendientes y tres dispositivos concedidos podrían recibir un FCM real. Se requiere autorización específica antes de abrir la compuerta y medir entrega real.
- `PRODUCTION_PUSH_INFRASTRUCTURE_BLOCKED`: Cron/PHP CLI en Neubox comprobados anteriormente, pero no hay Cron de notificaciones instalado, credencial de servidor PROD ni prueba productiva.
- `ANDROID_RELEASE_BLOCKED`: la configuración Firebase PROD y URL fuente son correctas; faltan firma de la APK instalada, keystore release compatible y confirmación de actualización sin pérdida de datos.
- `NOTIFICATIONS_PRODUCTION_DEPLOY_BLOCKED`: no hay credencial PROD ni verificación integral en celular/navegador o mediciones HTTP p50/p95 actuales.

## Evidencia DEV

`systemd --user` en WSL ejecuta `samy-push-dev.timer` cada minuto con `samy-push-dev.service`; el journal mostró ejecuciones a las 10:47, 10:48 y 10:49 CST, cada una terminada con `Procesados 0 intentos push`. La compuerta cerrada evita materializar los cuatro despachos existentes (IDs 17, 18, 22 y 24) o contactar FCM. La venta DEV #76 existe y no se modificó. El timer local depende de que WSL/laptop esté encendido; la propuesta de producción usa Cron de Neubox.

Con FCM simulado en SQLite aislada, los tests verifican: aviso persistente, outbox, entrega por dispositivo, aceptación, un solo envío en ejecución repetida, reintento temporal después de un minuto, invalidación `UNREGISTERED`, reclamo de `sending` solo tras cinco minutos, cancelación si rotó el token e historial sin marcar como leído. La prueba de compuerta cerrada verifica que el outbox queda pendiente. El envío real puede repetirse si el proceso muere después de que FCM acepta y antes de registrar el éxito: FCM HTTP v1 no ofrece una clave de idempotencia extremo a extremo. La clave única `(notification_id, device_id)` y el reclamo de fila evitan duplicados normales, no prometen exactamente una entrega en ese intervalo de fallo.

Para activar una prueba real DEV, primero revisar destinatarios y esos cuatro despachos; luego, con autorización específica, configurar `NOTIFICATIONS_PUSH_ENABLED=true` en DEV y confirmar la configuración cargada por Artisan. No basta con que el timer esté activo. Para detener nuevos envíos, poner la variable en `false` y limpiar caché de configuración si se usa.

## Costos, seguridad y dependencias

Cron en el plan Neubox actual: costo incremental estimado **$0 de servicio adicional**, sujeto a los límites de CPU, tiempo y procesos del contrato existente. MySQL, PHP CLI 8.2 y `flock` ya están disponibles; el espacio de tablas, consultas y tráfico consumen el plan actual. FCM figura sin costo en Spark. Un worker permanente también podría costar $0 adicional si Neubox lo permite, pero requiere supervisión y reinicio; no se ha confirmado esa capacidad. Un host externo 24/7 añade una cuota no cotizada y credenciales/acceso de red. Cloud Tasks puede tener operaciones gratuitas pero requiere configuración de Google Cloud y potencial facturación, por lo que no se selecciona ni activa.

Fuentes de precios: https://firebase.google.com/pricing y https://cloud.google.com/tasks/pricing. Los precios y límites deben reconfirmarse antes de contratar algo.

No existe endpoint público de ejecución push. El envío usa OAuth HTTP v1 en Laravel, con cuenta de servicio fuera de Git, APK, JS y `public_html`. El rol mínimo requerido para la cuenta PROD es `cloudmessaging.messages.create`, restringido a `samy-boutique`. Una aceptación de FCM no prueba entrega ni lectura.

## Configuración pendiente en Firebase PROD

Evidencia local: `android/app/src/release/google-services.json` apunta a `samy-boutique`, número `857960335792`, paquete `com.example.samy_boutique_app`; la documentación registra app Web, VAPID PROD y FCM HTTP v1 habilitado. No se consultó en vivo la consola/IAM de PROD en esta revisión, por lo que apps, VAPID, API y roles deben reconfirmarse allí antes del despliegue. Falta crear una cuenta de servicio de envío PROD con permiso mínimo, generar su clave por canal seguro, custodiarla fuera de `public_html` con permisos restrictivos e instalarla **solo durante el despliegue autorizado**. No tocar `APP_KEY` ni SMTP.

Variables productivas necesarias: `FIREBASE_PROJECT_ID=samy-boutique`, `FIREBASE_CREDENTIALS=/ruta/privada/...json`, `VITE_FIREBASE_PROJECT_ID`, `VITE_FIREBASE_API_KEY`, `VITE_FIREBASE_AUTH_DOMAIN`, `VITE_FIREBASE_STORAGE_BUCKET`, `VITE_FIREBASE_MESSAGING_SENDER_ID=857960335792`, `VITE_FIREBASE_APP_ID`, `VITE_FIREBASE_VAPID_KEY` PROD, `FIREBASE_JS_SDK_VERSION`, `NOTIFICATIONS_DELIVERY_BATCH_SIZE`, `NOTIFICATIONS_DELIVERY_TIMEOUT`, `NOTIFICATIONS_LOW_STOCK_THRESHOLD`, `LAYAWAY_EXPIRY_DAYS`, `NOTIFICATIONS_PUSH_ENABLED=true` al activar. Compilar Vite con variables PROD antes de subir activos. La credencial privada no debe entrar en variables `VITE_*`.

## Laravel, base de datos y rutas

Migraciones nuevas por aplicar en PROD: `2026_05_05_000001_create_notification_emails_table.php` y `2026_10_09_000001_create_business_notifications_tables.php`. La segunda crea `system_notifications`, `notification_preferences`, `notification_devices`, `notification_dispatches`, `notification_announcements`, `notification_inventory_states`, `notification_deliveries`, `notification_delivery_attempts` y agrega `layaways.expires_at`. En DEV la segunda ya corrió; la primera aparece pendiente en esa base, por lo que no se debe asumir uniformidad entre entornos.

Índices clave: aviso único por `(user_id,event_key)`, outbox único por `notification_id`, entrega única por `(notification_id,device_id)`, índice de entrega `(status,next_attempt_at)`, token hash, y consultas de bandeja `(user_id,read_at,created_at)`. El procesador limita lotes a 200, con valor por defecto 50; el comando programado procesa 5 por pasada. La búsqueda de destinatarios del anuncio manual se limita a 100 cuentas y la ruta a 5 solicitudes/minuto.

Permiso Spatie nuevo: `notifications.send`; resembrar con `php artisan db:seed --class=RolesAndPermissionsSeeder` y verificar asignaciones de gerente/administrador. Las rutas `/api/notifications*` y `/api/notification-devices*` están bajo autenticación API; el envío manual además requiere `can:notifications.send`. La web usa `/notifications`, `/sw.js` y `/firebase-messaging-sw.js`; Workbox se compila en `public/build`. `notifications:process-push`, `notifications:scan-inventory` y `notifications:scan-layaways` están registrados en `routes/console.php`. La cola SMTP `sale_notifications` continúa separada y apagada.

Cron productivo propuesto (sustituir la ruta real de la app tras verificarla):

```cron
*/5 * * * * /usr/bin/flock -n /home/makedweb/.samy_push.lock /usr/local/bin/ea-php82 /RUTA_PRIVADA_DE_LARAVEL/artisan notifications:process-push >> /RUTA_PRIVADA_DE_LOGS/push-cron.log 2>&1
```

La ruta `/usr/local/bin/ea-php82` fue comprobada como PHP CLI en Neubox; `/usr/bin/php` era CGI. Ajustar lote si el volumen supera cinco entregas por cinco minutos, verificando tiempo de ejecución, carga e índices. Para varios lotes por invocación se requiere cambio acotado del comando; no asumir que el valor de configuración altera el `processBatch(5)` actual. `flock` impide solapamiento de invocaciones Cron, y el bloqueo de fila protege frente a workers concurrentes normales.

## Android

La variante `normal` conserva `applicationId=com.example.samy_boutique_app`; el JSON `release` corresponde a Firebase PROD y la URL por defecto de Dart es `https://samy-boutique.makedweb.com/api`. El manifiesto incluye `POST_NOTIFICATIONS`; el registro y desvinculación por instalación, apertura de avisos y lectura se implementan en Flutter/Laravel. La variante normal aún se firma con la configuración **debug** según Gradle, también al producir `normalRelease`; no debe distribuirse como reemplazo hasta extraer la firma de la APK del cliente y comparar certificado SHA-256, paquete y `versionCode` con el keystore definitivo. Si no coinciden, no se puede instalar encima conservando datos mediante actualización normal. El keystore temporal de `perf` es independiente.

## QA y métricas

- Backend: `php artisan test` — 53 pruebas pasadas y 2 benchmarks opcionales omitidos; el benchmark HTTP se ejecutó por separado. Pint de los archivos PHP de esta intervención y `git diff --check` pasaron.
- Web: `npm run build` pasó; Workbox generó 64 entradas precargadas. No se ejecutó una prueba visual nueva de notificación/Web Push ni de modo offline.
- Android: `flutter analyze --no-fatal-infos` terminó sin errores, con avisos informativos; `flutter test` pasó (5 tests). `flutter build apk --debug --flavor normal` pasó; el APK DEV no es una distribución productiva.
- Rendimiento: en 20 solicitudes HTTP por fase contra SQLite de prueba, `POST /api/sales` con outbox tuvo p50/p95 de **15.71/20.37 ms** en la fase base y **15.39/19.18 ms** en la fase marcada FCM indisponible. Se registraron cero llamadas FCM durante checkout; las diferencias entre fases reflejan variación local, ya que FCM no participa en ninguna de ellas. Esto comprueba el aislamiento del request, no una latencia de red real ni el rendimiento de Neubox. El dato real productivo anterior de 934 ms sigue siendo una muestra aislada, sin p50/p95 productivo nuevo.
- E2E: sigue pendiente verificar recepción foreground/background, navegación, lectura y banner web tras resolver el service worker observado en el UAT previo. No se envió FCM real en esta intervención.

## Despliegue y rollback, pendientes de autorización

1. Confirmar mantenimiento y tomar copia verificable de base MySQL (incluidos `sales`, inventario, notificaciones, `jobs`) y copia de código/`storage`/configuración, cifrada y con acceso restringido. Verificar espacio, hash y restauración de muestra; registrar versión, hora y ruta de respaldos.
2. Inspeccionar `git diff`, secretos fuera de Git y `php artisan migrate:status` de PROD. Guardar export de permisos/roles. Confirmar `APP_KEY` y SMTP existentes sin modificarlos.
3. Subir Laravel y activos Vite ya compilados para PROD; aplicar `php artisan migrate --force` con las dos migraciones compatibles; ejecutar seeder de permisos y verificar tablas/índices/rutas con la compuerta push aún cerrada.
4. Instalar la credencial PROD privada, confirmar proyecto/permiso mínimo y configuración efectiva sin imprimir secretos. Crear el Cron dedicado con `flock`, dejando `NOTIFICATIONS_PUSH_ENABLED=false` hasta completar verificaciones de solo lectura.
5. Activar push, comprobar que Cron termina y que no toca `sale_notifications`. Validar un aviso manual a una cuenta de prueba únicamente con autorización específica; verificar bandeja, aceptación FCM, celular, navegador, lectura y logs de errores. No generar ventas productivas para la prueba.
6. Supervisar cola, despachos pendientes, entregas por estado, intentos, invalidaciones y latencia real; después distribuir solo una APK cuya firma se haya comparado con la instalada.
7. Rollback: cerrar la compuerta push y deshabilitar Cron/worker; restaurar el código anterior y activos web anteriores. Mantener tablas nuevas e historial para investigación. Si las migraciones requieren reversión, restaurar la base desde el respaldo validado en una ventana coordinada, preservando/conciliando las ventas posteriores; no usar `migrate:rollback` indiscriminadamente ni borrar datos de prueba.

No se hizo commit, push, deploy, cambio en Neubox, creación de credencial PROD ni envío FCM real.
