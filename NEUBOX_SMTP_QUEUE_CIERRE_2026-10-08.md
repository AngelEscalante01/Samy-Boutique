# Samy Boutique — cierre SMTP y Queue en Neubox

Fecha: 2026-10-08 (hora del hosting: America/Mexico_City). Evidencia obtenida con cPanel y un Cron temporal de solo lectura. No se modificaron el código, `.env`, la base comercial ni la configuración productiva. No se ejecutó worker, venta ni envío de correo.

## Estados

- `QUEUE_INFRASTRUCTURE_READY` — PHP CLI por Cron, arranque de Laravel, conexión MySQL, tablas de cola y permisos de escritura necesarios quedaron comprobados. Esta etiqueta describe el runtime y el esquema del hosting; la configuración `sale_notifications` sigue solo en el código local y no está desplegada.
- `SMTP_DIAGNOSTIC_BLOCKED` — se ejecutó una sola prueba SMTP autorizada en puerto 465, pero la excepción ocurrió durante `connect_and_authenticate` y el registro saneado no permite recuperar de forma fiable el código real. No se reintentó.
- `PRODUCTION_DEPLOY_BLOCKED` — no hay despliegue autorizado, la conexión dedicada aún no está en producción y SMTP sigue sin autenticación comprobada. También falta probar el límite real de duración/recursos del worker con una ejecución inocua que no procese jobs.

## Correo y rechazo 535

En cPanel → Email Accounts → Connect Devices, la cuenta usada por Samy Boutique aparece sin restricciones (Unrestricted). Connect Devices recomienda SMTP `mail.makedweb.com`, puerto 465 con SSL/TLS; muestra puerto 587 en “Non-SSL Settings (NOT Recommended)”. El nombre de usuario recomendado es la dirección completa y SMTP requiere autenticación.

El Cron leyó la configuración efectiva de Laravel sin mostrar valores secretos:

| Campo | Configuración efectiva en producción | Nota |
|---|---|---|
| mailer / transporte | `smtp` / `smtp` | Coincide con el uso de autenticación SMTP. |
| host | `mail.makedweb.com` | Coincide con Connect Devices. |
| port | `587` | cPanel lo lista en configuración no SSL/no recomendada; la recomendación es 465 SSL/TLS. |
| scheme | `null` | Producción no define `MAIL_SCHEME`. |
| username | configurado y coincide exactamente con el usuario completo de cPanel | El valor no se imprimió en el log. |
| from | `angelescalante@makedweb.com` | Coincide con la cuenta observada. |
| timeout | `null` | El límite de 10 s del código local aún no está en producción. |
| config cacheada | `false` | El 535 no se explica por una caché Laravel obsoleta en esta inspección. |

En Laravel 11/Symfony Mailer, con puerto 587 y `scheme` nulo el transporte es SMTP y Symfony puede negociar STARTTLS automáticamente. El 535 observado es un rechazo de autenticación; los datos disponibles no permiten determinar si la contraseña actual es inválida, si el proveedor rechaza esa cuenta/política, o si hay otra condición de autenticación. Usuario y host sí coinciden; la cuenta no aparece deshabilitada. Cambiar a puerto 465 puede alinear el transporte con la recomendación de cPanel, pero por sí solo no demuestra ni corrige el rechazo de credenciales.

En la prueba autorizada se aplicó temporalmente puerto 465, `MAIL_SCHEME=smtps` y timeout de 10 s solo en memoria antes de crear el transporte; no se modificaron `.env`, configuración ni caché productiva.

### Resultado del único intento autorizado

- Hora: `2026-10-08 15:40:11 -06:00`.
- Destino autorizado: `angelbambu123@gmail.com`; remitente leído desde la configuración Laravel existente. No hubo datos comerciales en el mensaje.
- Transporte: `mail.makedweb.com:465`, `smtps`, timeout 10 s.
- Duración medida: 7,873 ms.
- Fase: `connect_and_authenticate`; `Symfony\\Component\\Mailer\\Exception\\TransportException`.
- `transport->start()` lanzó la excepción antes de regresar. No se llamó a `Mailer::raw()`: no hay evidencia de que se haya enviado el cuerpo ni de aceptación SMTP. No se comprobó recepción en Gmail.
- El log sanitario informó `smtp_code=235`, `authentication=not_confirmed` y `smtp_message_acceptance=not_confirmed`. Ese código no es fiable: el saneador tomó el primer número de tres dígitos de la excepción y pudo haber capturado el código esperado por Symfony, no el recibido. No se conservó el mensaje crudo porque podía incluir datos de autenticación. Por ello, la autenticación queda indeterminada; no se declara ni `SMTP_AUTH_FAILED` ni `SMTP_ACCEPTED_PENDING_INBOX_CONFIRMATION`.
- El marcador persistente fue creado atómicamente antes del acceso SMTP; el log indica `attempts=1`. El siguiente disparo habría encontrado el marcador y terminado sin enviar. El Cron fue retirado antes de la siguiente ventana de cinco minutos. No se reintentó.

Cron usado:

```cron
*/5 * * * * umask 077; /usr/bin/flock -n /home/makedweb/.samy_smtp_diag_once_20261008.lock /usr/local/bin/ea-php82 /home/makedweb/samy_smtp_diag_once_20261008.php >> /home/makedweb/samy_smtp_diag_once_20261008.log 2>&1
```

Después del intento, cPanel volvió a mostrar `No Cron Jobs`. El script, el log, el marcador y el lock se movieron a la papelera recuperable; el log técnico y el resultado se conservaron en este informe local. No se vació la papelera.

El 535 anterior continúa sin diagnóstico concluyente. Una nueva prueba requiere nueva autorización expresa; el siguiente script debe sanear el mensaje Symfony de modo que distinga el código esperado del recibido y debe registrar el resultado del transporte sin exponer secretos. La aceptación SMTP, si se confirma, aún no probaría entrega a Gmail. No cambiar contraseña ni `APP_KEY`.

## Cron, PHP CLI, base de datos y permisos

Se ejecutó una vez el comando temporal siguiente, cada cinco minutos, bajo el usuario cPanel:

```cron
*/5 * * * * /usr/local/bin/ea-php82 /home/makedweb/samy_queue_preflight_20261008.php > /home/makedweb/samy_queue_preflight_20261008.log 2>&1
```

Resultado del log, ejecución 2026-10-08 15:25:02 CST (21:25:02 UTC):

- PHP CLI `/usr/local/bin/ea-php82` ejecutó el script; Artisan y `vendor/autoload.php` eran legibles.
- Laravel arrancó como versión 11.48.0; configuración no cacheada.
- `openssl` y `pdo_mysql` presentes en esta ejecución. La verificación previa de PHP CLI confirmó además PHP 8.2.34, `pcntl`, PDO y `pdo_mysql`; `flock` y `timeout` están en `/usr/bin/flock` y `/usr/bin/timeout`.
- `SELECT 1` a MySQL tuvo éxito. Existen `jobs` y `failed_jobs`; el conteo inspeccionado para `sale_notifications` fue 0 y `failed_jobs` fue 0. La inspección fue de solo lectura.
- `storage/framework`, `storage/framework/cache` y `storage/logs` existían y permitieron crear, leer y borrar un archivo temporal bajo el usuario del Cron. La prueba de ida y vuelta de cache de Laravel también pasó.
- Esto valida permisos y conectividad del usuario Cron, pero no el límite máximo de ejecución, memoria, CPU ni un ciclo real de worker.
- El código productivo respondió `QUEUE_CONNECTION=sync`; la conexión `sale_notifications`, su cola, retraso y `retry_after` no aparecen en la configuración efectiva. Por tanto, todavía no se debe arrancar el comando worker.

Después de guardar la evidencia, el Cron temporal se eliminó desde cPanel; la tabla mostró `No Cron Jobs`. Los archivos `/home/makedweb/samy_queue_preflight_20261008.php` y `/home/makedweb/samy_queue_preflight_20261008.log` se movieron a la papelera recuperable del File Manager y ya no aparecen en HOME. No se vació la papelera. Ningún Cron de diagnóstico quedó activo.

## Sendmail local

Estos ejecutables existen y tenían modo `0755`: `/usr/sbin/sendmail`, `/usr/lib/sendmail` y `/usr/local/cpanel/bin/sendmail`. Solo se comprobaron existencia y permisos; no se ejecutaron. Esto no demuestra que el MTA entregue correo, respete cuotas o tenga buena reputación. No recomiendo cambiar `MAIL_MAILER` a `sendmail` hasta hacer una prueba autorizada de entrega y revisar límites/política del hosting. No es una solución confirmada al 535 SMTP.

## Worker propuesto (no instalado)

Una vez desplegada y validada la conexión de cola:

```cron
*/5 * * * * /usr/bin/flock -n /home/makedweb/.sale-notifications.lock /usr/bin/timeout --signal=TERM --kill-after=5s 135s /usr/local/bin/ea-php82 /home/makedweb/public_html/samy-boutique/artisan queue:work sale_notifications --queue=sale-notifications --sleep=0 --tries=3 --timeout=60 --max-time=60 --max-jobs=20 --stop-when-empty >> /home/makedweb/sale-notifications-worker.log 2>&1
```

El código local limita cada job a 60 s, tres intentos y backoff acotado; `retry_after=180` deja 40 s después del límite externo nominal de 140 s. `flock` evita solapamiento; cada ejecución drena como máximo 20 jobs y sale si la cola está vacía. El worker no está creado en cPanel. La primera activación debe probar el comando con cola dedicada vacía, y luego observar una única notificación de prueba autorizada antes de permitir notificaciones reales. No registrar payloads ni direcciones de clientes en logs.

## QA local

Resultados de la rama local `codex/perf-sale-api`, sin commit ni push:

- `php artisan test`: 42 pruebas aprobadas, 302 aserciones, 1 benchmark optativo omitido en la suite.
- Pint focalizado en los archivos relacionados: PASS.
- `git diff --check`: PASS.
- Benchmark optativo con transporte SMTP lento/fallido simulado: 10 muestras por caso y 20 jobs encolados. HTTP legado modelado: mediana 20,004.45 ms / p95 20,025.14 ms; HTTP encolado: mediana 4.75 ms / p95 51.84 ms. El intento simulado posterior al cobro tardó 112.79 ms.

El benchmark simula esperas; no contactó SMTP real y no mide producción. Las pruebas locales cubren confirmación de venta independiente de SMTP, despacho después del commit, reintentos limitados y worker acotado. El backend no espera el resultado de impresión; la desconexión física de la impresora no se probó en una tablet durante este cierre. Android y web conservan el contrato JSON según las pruebas del repositorio.

## Diferencia de latencia observada

Los cobros reales reportados fueron 20,696 ms y 21,175 ms; la medición local histórica fue aproximadamente 485 ms promedio. El benchmark de este turno muestra que el diseño encolado responde rápido en el entorno local aun con SMTP lento/fallido simulado, pero el código no está desplegado. No se capturó duración de `POST /api/sales` del lado servidor para esas dos solicitudes reales, por lo que no se puede repartir la diferencia entre backend productivo, red, Android y espera del cliente ni atribuir esos ~21 s a Laravel. Para confirmarlo hace falta desplegar tras respaldo y autorización, registrar correlación y tiempos server-side sin datos de clientes, y repetir cobros controlados con APK PERF; no ejecutar cobros de diagnóstico en producción.

## Activación y rollback

1. Antes de desplegar, respaldar archivos y exportar la base; verificar que ambos respaldos se puedan leer.
2. Comparar producción con `codex/perf-sale-api` y preparar una lista exacta de archivos. Excluir `.env`, `APP_KEY`, secretos, APK/Flutter y cambios ajenos al correo salvo aprobación aparte. No volver a aplicar migraciones si las tablas necesarias ya existen.
3. Resolver autenticación SMTP con una prueba de un mensaje expresamente autorizada. Revisar el resultado SMTP y, cuando corresponda, la llegada al buzón. Alinear host, puerto y scheme con Connect Devices.
4. Desplegar el código revisado en una ventana controlada, conservando `.env` y `APP_KEY`; aplicar la configuración de queue y timeout prevista. Refrescar config cache solo como parte del despliegue planeado y verificar la configuración efectiva sin imprimir secretos.
5. Crear el Cron de cinco minutos con `flock` y `timeout`; primero confirmar salida limpia con cola dedicada vacía. Luego autorizar una notificación aislada y revisar conteos de `jobs`/`failed_jobs` y entrega.
6. Repetir medición del cobro desde APK PERF y web. Comparar tiempo HTTP, salida del servidor y espera de correo por separado.
7. Rollback: desactivar primero el Cron, conservar `jobs`/`failed_jobs` y las clases requeridas para jobs serializados, restaurar los archivos anteriores y verificar contrato/API. No borrar trabajos pendientes ni restaurar una base anterior sobre nuevas ventas.

## Evidencia que falta

- Autorización y resultado de un único correo de prueba a un destinatario definido.
- Confirmación de aceptación SMTP y comprobación independiente de entrega al buzón.
- Prueba inocua de duración/memoria disponible para un proceso CLI de Cron; no hay medición actual de CPU/RAM del worker.
- Comparación final de hashes/código productivo y paquete de respaldo para una ventana de despliegue.
- Worker desplegado y proceso real observado. Sin eso no se declara entrega productiva PASS.
