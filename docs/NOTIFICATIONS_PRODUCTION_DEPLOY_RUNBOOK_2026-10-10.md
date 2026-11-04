# Samy Boutique — preparación de notificaciones PROD (10 oct 2026)

Estado: **SAMY_NOTIFICATIONS_PRODUCTION_BLOCKED**. Esta revisión no cambió Neubox, Firebase PROD ni la APK del cliente; no hizo commit, push ni envíos FCM. El E2E automático DEV ya aprobado se conserva en `FCM_AUTO_DEV_E2E_2026-10-10.md`.

## 1. Checkpoint y alcance del paquete

| Repositorio | Rama / HEAD | Estado |
| --- | --- | --- |
| Laravel, `Samy-Boutique` | `codex/perf-sale-api` / `65b3f73eed58e4d68be0bfd01edb755c8279b60a` | 23 archivos rastreados modificados, más código, migraciones, pruebas, documentación y ops nuevos sin rastrear. |
| Flutter, `samy-boutique-app` | `codex/perf-checkout-printing` / `461a7fe7f8231e4353b9640995b5f343b8a24e8a` | 24 archivos rastreados modificados, más Firebase, avisos, rendimiento y pruebas nuevos sin rastrear. |

El paquete Laravel debe incluir **todo el diff actual revisado**, incluidas las correcciones de cobro rápido, SMTP diferido, avisos, Web Push y service worker. No desplegar únicamente los archivos de notificaciones sobre una versión antigua. El código del checkout crea el aviso/outbox en MySQL y registra el correo en `sale_notifications` tras el commit; el envío SMTP y el HTTP FCM no ocurren dentro de `POST /api/sales`. La entrada push de Laravel Scheduler por minuto se quitó en este checkpoint: el Cron dedicado será la única programación PROD. El timer DEV sigue invocando el comando directamente.

La inspección local no prueba qué archivos están desplegados hoy en Neubox. La documentación del 8 oct confirma que existían `notification_emails`, `jobs` y `failed_jobs` y que la corrección de cobro rápido estaba preparada; no confirma el árbol productivo actual. El 10 oct, una consulta HTTP pública devolvió `200` en `/login` y `404` en `/sw.js` y `/firebase-messaging-sw.js`; estas dos rutas Web Push aún no están disponibles públicamente. Hay que comparar hashes/contenido de `CreateSaleAction.php`, `NotificationService.php`, `config/queue.php`, rutas y activos con PROD antes de copiar. No sustituir una corrección productiva más reciente por un archivo local antiguo.

## 2. Firebase PROD y secretos

Configuración local: proyecto `samy-boutique`, número/sender ID `857960335792`; Android normal `com.example.samy_boutique_app`; app Android `1:857960335792:android:a8f96bfa2f140ab4d36e75`; app Web `1:857960335792:web:a8c872579f0d7ecfd36e75`. El JSON de `android/app/src/release` apunta a PROD. El VAPID Web PROD consta en `NOTIFICACIONES_FIREBASE.md`. La consola/IAM PROD no pudo verificarse en vivo en esta revisión: reconfirmar apps, SHA de Android, VAPID y API FCM HTTP v1 antes de activar.

Pendiente y **no autorizada aún**: crear cuenta de servicio exclusiva de PROD y credencial privada. Conceder en el proyecto `samy-boutique` una función personalizada restringida a `cloudmessaging.messages.create`, si IAM la admite; verificarla sin añadir permisos amplios. Google documenta ese permiso para FCM HTTP v1. Guardar el JSON fuera de `public_html`, Git, respaldos públicos y APK, propiedad del usuario que ejecuta PHP, directorio `0700` y archivo `0600`. Nunca usar la cuenta DEV. Registrar responsable y rotación de clave.

En `.env` productivo: `FIREBASE_PROJECT_ID=samy-boutique`; `FIREBASE_CREDENTIALS` con ruta privada absoluta; `VITE_FIREBASE_*` correspondientes **todos** a PROD (en particular `PROJECT_ID`, sender ID, app ID Web y VAPID), `APP_URL=https://samy-boutique.makedweb.com`, timeout y batch. Mantener `NOTIFICATIONS_PUSH_ENABLED=false` durante instalación. `.env.example` contiene valores DEV y nunca debe copiarse como `.env` PROD. Compilar Vite con variables PROD antes de subir `public/build`; el build local de QA usa DEV y no es distribuible.

Referencias oficiales: [autorización FCM HTTP v1](https://firebase.google.com/docs/cloud-messaging/send/v1-api), [permisos IAM FCM](https://docs.cloud.google.com/iam/docs/roles-permissions/firebasecloudmessaging).

## 3. Laravel, MySQL y cPanel

Migraciones del paquete: `2026_05_05_000001_create_notification_emails_table.php` y `2026_10_09_000001_create_business_notifications_tables.php`. Según la evidencia del 8 oct, la primera ya se aplicó en PROD; comprobar con `migrate:status` antes de `migrate --force` y resolver cualquier discordancia entre tabla existente y tabla `migrations` sin repetir `CREATE TABLE`. La segunda crea `system_notifications`, `notification_preferences`, `notification_devices`, `notification_dispatches`, `notification_announcements`, `notification_inventory_states`, `notification_deliveries` y `notification_delivery_attempts`, y agrega `layaways.expires_at`. Verificar tablas, índices únicos, claves foráneas y espacio MySQL.

También llevar servicios/modelos/controlador, `config/firebase.php`, rutas API/web/console, middleware/permiso `notifications.send`, seeder de roles, vistas de avisos, JS y `public/build` compilado con PROD. `/sw.js` y `/firebase-messaging-sw.js` usan el mismo Workbox bajo el origen web; verificar MIME, HTTPS, alcance y ausencia de caché DEV tras actualización. API de dispositivos, preferencias y bandeja requiere autenticación; anuncio manual requiere permiso y límite de 5/min. Token del dispositivo está cifrado en el modelo. El navegador aísla almacenamiento por origen, pero un bundle compilado con DEV registraría tokens equivocados en PROD; comprobar configuración efectiva y renovar token si hubo mezcla.

El seeder `RolesAndPermissionsSeeder` agrega el permiso a los roles previstos. Revisar asignaciones actuales antes/después, sin resetear roles. Conservar `APP_KEY`, SMTP, `QUEUE_CONNECTION` y la conexión separada `sale_notifications`; no iniciar worker SMTP ni procesar sus trabajos. La salida de inventario/apartados del Scheduler requiere revisar aparte si existe `schedule:run` en cPanel.

## 4. Cron FCM propuesto, todavía no creado

Preparar `/home/makedweb/samy_push_logs` (`0700`), archivo `cron.log` (`0600`) con rotación y `/home/makedweb/.samy_push.lock` fuera de `public_html`. Verificar rutas reales de `flock` y `timeout` en Neubox, usuario cPanel y permisos de escritura antes de instalar. PHP CLI confirmado: `/usr/local/bin/ea-php82`; Artisan: `/home/makedweb/public_html/samy-boutique/artisan`.

```cron
*/5 * * * * /usr/bin/flock -n /home/makedweb/.samy_push.lock /usr/bin/timeout --signal=TERM --kill-after=10s 180s /usr/local/bin/ea-php82 /home/makedweb/public_html/samy-boutique/artisan notifications:process-push >> /home/makedweb/samy_push_logs/cron.log 2>&1
```

El comando procesa hasta **5 entregas** por ejecución. El outbox deduplica avisos por evento, despachos por aviso y entregas por aviso/dispositivo; reclama filas, recupera `sending` tras 5 min, cancela token rotado/desvinculado, reintenta fallos temporales a 1/5/20/60/180 min e invalida `UNREGISTERED`. Registrar estado, duración, número pendiente y errores sin tokens ni cuerpos sensibles. `flock` impide solapamiento del Cron; no ejecutar simultáneamente otro Scheduler/worker push. La entrega es al menos una vez: una caída tras aceptación de FCM y antes de guardar el resultado puede duplicar el mensaje. Latencia inicial aproximada 0–5 min más procesamiento/reintentos; no anunciar segundos. Capacidad nominal máxima 5 cada 5 min (60/h), por revisar con volumen real.

## 5. Android instalada y actualización

Leído del Redmi Note 8 Pro del cliente: paquete `com.example.samy_boutique_app`, `versionCode=1`, `versionName=1.0.0`; certificado SHA-256 `05:EF:D9:CE:7B:62:43:F2:5A:37:2D:A5:CE:42:38:44:79:67:C8:2B:10:69:AF:7D:AE:FA:55:C2:F8:E0:29:CA`. Es un certificado **Android Debug**. Coincide con `/home/angel/.android/debug.keystore` local. Gradle firma `normalRelease` con `debug`; `pubspec.yaml` aún indica `1.0.0+1`.

Una actualización in situ exige el mismo `applicationId`, certificado compatible y `versionCode>1`; conservar la app instalada y sus datos, sin desinstalar. Para continuidad inmediata se puede preparar una APK normal PROD con ese keystore y subir `versionCode`, previa autorización y resguardo del keystore; seguir con firma debug perpetúa una clave de desarrollo en distribución. Para migrar a firma release distinta, planificar una estrategia de rotación de firma compatible con el Android 11 instalado y probarla en copia/dispositivo equivalente antes de entregar; si no resulta viable, requerirá migración respaldada de datos y acuerdo explícito con el cliente. No se generó APK nueva.

## 6. Secuencia de despliegue autorizado y verificación

1. En ventana acordada, inventariar versión PROD, Cron actual, variables efectivas sin imprimir secretos, `migrate:status`, tablas, permisos, SHA de archivos y activos. Comparar específicamente checkout rápido y conservar cambios PROD más nuevos. Confirmar espacio y permisos.
2. Tomar copia privada y verificable de árbol de aplicación, `storage`, `.env` y `public/build`; respaldar MySQL completo con ventas, inventario, jobs y tablas existentes. Guardar hashes, hora y prueba de lectura/restauración de muestra. Proteger respaldos.
3. Preparar paquete completo desde este checkpoint revisado y compilar frontend con variables Web Firebase PROD. Subir de forma controlada sin sobrescribir `.env` ni `storage`. Mantener push deshabilitado.
4. Aplicar migraciones pendientes con `/usr/local/bin/ea-php82 artisan migrate --force`, verificar estado, tablas e índices. Ejecutar seeder de roles y comparar permisos. Refrescar configuración/rutas según caché usada. Probar login, ventas y checkout contra la app actual sin disparar correos ni FCM reales adicionales.
5. Tras autorización separada, instalar credencial PROD privada y verificar que proyecto de JSON, IAM, API v1 y configuración web/Android coinciden. Verificar service worker y Centro de avisos con cuenta de prueba, permisos, preferencias, bandeja y aislamiento de tokens.
6. Crear el único Cron push y activar `NOTIFICATIONS_PUSH_ENABLED=true` solo después de revisar destinatarios/outbox PROD. Una prueba real controlada de envío requiere autorización específica y destinatario de prueba. Comprobar aceptación FCM y recepción visual en Android/Web; una respuesta 200 no demuestra visualización.
7. Vigilar logs privados, estados de outbox y entregas, errores, backlog, tiempos y latencia de cobro; distribuir APK compatible solo tras validar actualización in situ.

## 7. Rollback

Cerrar primero `NOTIFICATIONS_PUSH_ENABLED=false` y deshabilitar Cron push. Restaurar código y activos previos con la copia tomada justo antes, preservando `.env`, `APP_KEY`, `storage` y correcciones de cobro/ventas que hayan llegado entretanto. Mantener tablas nuevas y registros para diagnóstico si la aplicación anterior los tolera; **no** ejecutar `migrate:rollback` a ciegas porque borra avisos y cambia `layaways`. Si hace falta volver la base, ensayar restauración del respaldo completo y conciliar explícitamente ventas, inventario, apartados, jobs y avisos creados desde el respaldo antes de sustituir datos. Rotar/revocar credencial PROD si hubo exposición. No tocar `sale_notifications` durante el rollback push.

## 8. QA y bloqueos de salida

QA local: `php artisan test` 53 pasadas, 2 benchmarks opcionales omitidos; `npm run build` pasó con Workbox de 64 entradas; `flutter analyze --no-fatal-infos` pasó con 95 avisos informativos; `flutter test` 5 pasadas; `git diff --check` sin errores. Ninguna prueba de QA envió FCM real. El build Web fue local con variables DEV y debe repetirse con PROD. Falta verificar directamente archivos/BD/Cron de Neubox y consola/IAM Firebase PROD, preparar credencial bajo autorización, respaldo productivo fresco, compilar/distribuir bundle PROD, instalar Cron y realizar UAT visual PROD. Hasta entonces el estado sigue **SAMY_NOTIFICATIONS_PRODUCTION_BLOCKED**.
