# Samy Boutique — preparación de cobros rápidos y diagnóstico SMTP

Fecha: 2026-10-08 (America/Mexico_City)

Estado: `SALE_FAST_DEPLOY_PREPARED`

SMTP: `SMTP_AUTH_PENDING`; TLS se diagnosticó, pero autenticación y entrega no están comprobadas.

## Diagnóstico SMTP

Desde Neubox, `mail.makedweb.com` resolvió a `72.249.55.42`; TCP 465 conectó, TLS negoció con validación de certificado y nombre habilitada, y el servidor presentó banner Exim 220. Resultado: 2026-10-08 15:50:01 -06:00, duración total 273 ms. Certificado `*.makedweb.com`, vencimiento 2027-01-03 22:27:54 UTC. La prueba solo leyó el banner. No envió EHLO, AUTH, MAIL FROM, RCPT TO ni DATA.

La prueba autenticada anterior falló en `transport->start()` tras 7,873 ms, antes de `Mailer::raw()`. El registro conservó `Symfony\\Component\\Mailer\\Exception\\TransportException` y la fase amplia `connect_and_authenticate`, pero no guardó la excepción original ni el transcript. El sanitizador tomó el primer código de tres dígitos (`235`), que pudo ser el código esperado mencionado en el texto y no la respuesta recibida. No demuestra autenticación ni permite distinguir TLS, timeout o rechazo de credenciales.

Symfony `EsmtpTransport::start()` puede hacer EHLO, negociar STARTTLS si corresponde y ejecutar AUTH. Las excepciones SMTP pueden adjuntar el buffer `getDebug()` del stream, que puede contener diálogo AUTH y datos sensibles; no se recuperó ni imprimió. La prueba TLS se hizo con socket independiente y sin autenticación.

El Cron temporal fue `*/5 * * * *`, con `/usr/bin/flock -n` y `/usr/local/bin/ea-php82`. Marcador exclusivo antes de la conexión aseguró un solo intento. Después de recoger el log privado, se retiró el Cron y cPanel mostró **No Cron Jobs**. Script, log, marcador y lock ya no aparecen en `/home/makedweb`; se movieron a la papelera recuperable, que no se vació. No se ejecutó Artisan ni se consultó o procesó MySQL durante esta prueba.

## Cola y seguridad de venta

En local, `CreateSaleAction` confirma la transacción de venta/inventario y luego llama `NotificationService`. El servicio usa `DB::afterCommit()` y despacha un job por destinatario en la conexión explícita `sale_notifications`, driver `database`, tabla `jobs`, queue `sale-notifications`, demora configurable de 60 s y `retry_after=180`. SMTP solo aparece en `SendSaleNotification::handle()`. La venta no espera SMTP ni Bluetooth; un fallo al registrar la cola se captura después del commit y no se transforma en error HTTP.

`QUEUE_CONNECTION=sync` no cambia esa conexión explícita: `onConnection('sale_notifications')` usa el driver `database`. Con worker apagado, los jobs quedan en MySQL y sobreviven reinicios sin intentar SMTP. La unicidad venta+destinatario (`ShouldBeUnique`, 24 h) reduce despachos repetidos mientras exista el lock. No garantiza entrega exactamente una vez: un SMTP que acepta el mensaje y pierde la respuesta puede provocar duplicado en el reintento.

La inspección productiva anterior confirmó InnoDB y migraciones aplicadas para `jobs`, `failed_jobs` y `notification_emails`; el último conteo observado fue 0 filas en `jobs` y 0 en `failed_jobs`. Esta tarea no volvió a consultar la base; ese 0 es el último corte, no un conteo actual. No se procesaron jobs. No hace falta una migración nueva si una verificación de solo lectura antes del despliegue confirma esas tablas y sus filas en `migrations`.

No encontramos idempotencia server-side en `POST /api/sales`. Si el backend confirma la venta pero el cliente pierde la respuesta y reintenta, puede crear otra venta (si queda inventario) y otro conjunto de notificaciones. `DB::afterCommit()` separa el alta de job del commit de la venta; una caída justo después del commit puede perder una notificación. Para una garantía mayor se requeriría un outbox transaccional. No se amplió el código en esta preparación.

## Lote mínimo preparado para desplegar, sin ejecutarlo

Desplegar estos siete archivos y mantener el worker apagado:

1. `app/Actions/Sales/CreateSaleAction.php`
2. `app/Services/NotificationService.php`
3. `app/Jobs/SendSaleNotification.php`
4. `app/Models/NotificationEmail.php`
5. `app/Mail/SaleNotificationMail.php`
6. `resources/views/emails/sale_notification.blade.php`
7. `config/queue.php`

Excluir del lote `app/Http/Controllers/Api/BusinessApiController.php`, `bootstrap/app.php` y `app/Http/Middleware/SalePerfAudit.php`, que son instrumentación de rendimiento separada. No se necesitan pruebas, `.env.example`, migración (si está aplicada) ni `config/mail.php` para cobrar rápido con el worker apagado. La corrección de `config/mail.php` queda para una fase separada, después de resolver SMTP. No modificar `.env`, credenciales, `APP_KEY` ni caché en este preflight.

El contrato JSON 201 sigue usando `saleData`; las pruebas locales cubren checkout web y móvil por la misma acción, jobs posteriores al commit, fallo SMTP confinado al job, reintentos y unicidad.

### Pasos, respaldo y rollback

1. Respaldar los siete archivos productivos y la versión anterior de `config/queue.php`; guardar hashes y no incluir `.env` en el paquete.
2. Desplegar solo esos archivos en ventana controlada y comparar hashes. No subir todo el checkout.
3. Mantener sin worker, sin Cron de cola, sin `queue:work`, sin cambio de `QUEUE_CONNECTION`, sin prueba de venta productiva y sin migraciones.
4. Verificar por lectura la conexión database, tabla `jobs`, queue `sale-notifications`, tablas/migraciones, logs y health check HTTP inocuo. No mostrar payloads.
5. Monitorear solo conteos agregados diariamente. Umbral recomendado: aviso con 500 pendientes o antigüedad >24 h; al llegar a 1,000 o 7 días, detener el despacho de notificaciones con decisión operativa y resolver capacidad. Este límite no está automatizado; las ventas deben seguir confirmándose aunque se suspendan notificaciones.
6. Tras resolver SMTP y autorizar/comprobar una prueba aparte, desplegar `config/mail.php` si aplica y activar un worker acotado únicamente a `sale-notifications`, inicialmente hasta 10 jobs por corrida, timeout 60 s, `retry_after=180`, tres intentos, backoff 60/300 s, `flock` y cadencia de cinco minutos aprobada. Revisar `jobs`/`failed_jobs` por conteo; reintentar fallos de forma selectiva, no vaciar tablas ni reintentar ciegamente.
7. Rollback: detener cualquier worker, restaurar los siete archivos respaldados y probar health/API. Conservar `jobs` y `failed_jobs`; no restaurar una base anterior sobre ventas nuevas. Mantener clases compatibles con jobs serializados pendientes.

## QA local

- `php artisan test`: 42 pasaron, 302 aserciones, 1 benchmark opcional omitido.
- `vendor/bin/pint --test` sobre los ocho archivos afectados: OK.
- `git diff --check`: OK.

La evidencia TLS confirma DNS/TCP/TLS/banner en ese momento; no demuestra AUTH ni entrega. No hubo correo, venta productiva, migración, cambios Laravel en hosting, Cron definitivo, deploy, push o commit.
