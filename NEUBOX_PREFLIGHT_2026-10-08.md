# Samy Boutique — preflight de cola en Neubox (2026-10-08)

## Estado

SMTP_AUTH_VALIDATION_PENDING; todavía no declarar NEUBOX_DEPLOY_READY. Cron y PHP CLI quedaron comprobados y Artisan productivo arrancó correctamente. El worker no se activó; el SMTP mantiene el error 535 previo y no se envió ningún correo real. También falta verificar escritura en storage/cache y logs bajo el usuario Cron.

No se hizo deploy, commit, push, venta, migración, cambio de .env, cambio de credenciales ni envío SMTP.

## Evidencia cPanel

- Instalación: /home/makedweb/public_html/samy-boutique.
- Artisan: /home/makedweb/public_html/samy-boutique/artisan.
- Existe vendor/autoload.php.
- Cron temporal ya eliminado: */5 * * * * /usr/local/bin/ea-php82 /home/makedweb/public_html/samy-boutique/artisan --version >> /home/makedweb/samy_artisan_diag_20261008.log 2>&1.
- Log privado fuera de public_html: /home/makedweb/samy_artisan_diag_20261008.log; dos ejecuciones dieron Laravel Framework 11.48.0, sin errores visibles.
- cPanel quedó en No Cron Jobs. No existe worker productivo programado.
- PHP CLI: /usr/local/bin/ea-php82, PHP 8.2.34 CLI NTS, pcntl, PDO, pdo_mysql; también /usr/bin/flock y /usr/bin/timeout. No usar /usr/bin/php (CGI/FastCGI).
- Las tablas jobs, failed_jobs, notification_emails y cache constan en inspección previa de phpMyAdmin. No se requiere recrearlas. Artisan --version no prueba permisos de escritura de storage/cache ni límites reales del worker.

## Configuración productiva observada de forma filtrada

- QUEUE_CONNECTION=sync; el código local de ventas despacha explícitamente por sale_notifications y no depende de la conexión default.
- DB_QUEUE_TABLE, DB_QUEUE_CONNECTION y SALE_NOTIFICATION_DELAY_SECONDS no están definidos. Laravel usa jobs; la demora local default es 60 s.
- CACHE_STORE=file. ShouldBeUnique necesita escritura en cache; permisos para PHP CLI siguen pendientes.
- SMTP: MAIL_MAILER=smtp, MAIL_HOST=mail.makedweb.com, puerto 587, remitente angelescalante@makedweb.com (Samy Boutique). Usuario y contraseña están configurados; no se leyeron ni expusieron sus valores.
- Producción define MAIL_ENCRYPTION=tls, pero no MAIL_SCHEME. El config/mail.php local consume MAIL_SCHEME; con puerto 587 Laravel elige SMTP y Symfony intenta STARTTLS automáticamente. El handshake no se probó en Neubox. Esta diferencia de nombre no explica por sí sola el 535.
- El 535 sigue indicando autenticación rechazada. Tener usuario y contraseña configurados no prueba que el servidor los acepte. Confirmar con el proveedor formato del usuario, credencial vigente, política SMTP y remitente permitido, sin cambiar secretos en este preflight.

## Código y compatibilidad

Rama local codex/perf-sale-api. Se conservaron los cambios existentes. En este turno se ajustó retry_after dedicado de 120 a 180 s y se añadieron dos pruebas del worker limitado.

- CreateSaleAction confirma venta/inventario y luego registra el despacho con DB::afterCommit.
- NotificationService crea un job por destinatario en conexión database sale_notifications, cola sale-notifications, demora default 60 s. Un error de despacho no revierte la venta.
- SendSaleNotification es el único lugar que envía SMTP; el request no espera el correo. Job timeout 60 s, 3 intentos, backoff 60/300 s y unicidad por venta/destinatario por 24 h.
- La impresión ocurre después de confirmar la venta; no bloquea worker ni correo.
- ShouldBeUnique evita duplicados mientras exista el lock de cache; no da garantía exactly-once si SMTP acepta el mensaje y se pierde la respuesta. Reintentos ambiguos pueden duplicar correo. Garantía histórica requiere outbox/registro de entrega con clave única venta-destinatario y reconciliación.
- La migración notification_emails y tablas estándar de cola/cache ya constan aplicadas en producción; no se identificó migración necesaria para esta activación.

## Worker propuesto (no instalado)

    /usr/bin/flock -n /home/makedweb/.sale-notifications.lock /usr/bin/timeout --signal=TERM --kill-after=5s 135s /usr/local/bin/ea-php82 /home/makedweb/public_html/samy-boutique/artisan queue:work sale_notifications --queue=sale-notifications --sleep=0 --tries=3 --timeout=60 --max-time=60 --max-jobs=20 --stop-when-empty >> /home/makedweb/sale-notifications-worker.log 2>&1

Cron propuesto: */5 * * * *. No se creó.

Un job puede tardar hasta 60 s. --max-time=60 se revisa entre jobs y no interrumpe uno activo. timeout envía TERM a 135 s y KILL cinco segundos después (máximo aproximado 140 s); retry_after=180 deja 40 s de margen. flock evita solapamiento; la cola explícita excluye otras colas; 20 jobs y --stop-when-empty limitan cada ejecución. Cada notificación puede esperar hasta cinco minutos al siguiente Cron, más los 60 s iniciales. Esto se probó localmente, pero no bajo los límites de CPU/memoria/procesos del hosting.

## Pruebas

- php artisan test: 42 aprobadas, 302 aserciones, 1 omitida (benchmark optativo, ejecutado aparte).
- vendor/bin/pint --test en seis archivos relacionados: PASS. Pint en todo el repo reportó 34 problemas en archivos preexistentes/ajenos; no se reformatearon.
- git diff --check: PASS.
- Worker local procesó solo sale-notifications, dejó intacto el job default, terminó al vaciar la cola y no duplicó el correo en una segunda ejecución.
- SMTP 535 simulado tres veces terminó en failed_jobs; la venta e inventario quedaron confirmados.
- Benchmark optativo: 10 ventas por escenario, 20 trabajos. Escenario síncrono suma dos esperas supuestas de 10 s (no duerme 20 s ni conecta a SMTP): HTTP modelado, mediana 20,002.96 ms / p95 20,012.64 ms. Cola local: mediana 4.38 ms / p95 17.63 ms. Un intento SMTP simulado después del cobro duró 109.42 ms. Son mediciones locales, no tiempos productivos ni prueba SMTP real.

## Despliegue, respaldo y rollback

Archivos funcionales locales: app/Actions/Sales/CreateSaleAction.php, app/Services/NotificationService.php, app/Jobs/SendSaleNotification.php, app/Models/NotificationEmail.php, app/Mail/SaleNotificationMail.php, resources/views/emails/sale_notification.blade.php, config/queue.php, config/mail.php y .env.example. La migración local 2026_05_05_000001_create_notification_emails_table.php no debe aplicarse si se confirma su presencia en producción. Pruebas no se despliegan. Cambios separados de rendimiento en BusinessApiController.php, bootstrap/app.php y SalePerfAudit.php deben revisarse aparte. No hay cambios Flutter.

1. Antes de despliegue, respaldar código productivo y exportar DB desde phpMyAdmin fuera de public_html; comprobar que los respaldos se puedan leer.
2. Comparar hashes/diff local-producción y confirmar compatibilidad de vendor. Aprobar una lista exacta; excluir .env, APP_KEY, credenciales, Flutter y cambios de rendimiento separados salvo aprobación.
3. Verificar permisos CLI de storage/framework/cache, storage/logs y acceso DB. Confirmar SMTP con el proveedor y realizar prueba real únicamente con autorización aparte.
4. No correr migraciones si tablas/esquema siguen presentes. Subir solo el paquete revisado y habilitar el Cron propuesto después de validar SMTP, permisos y límites.
5. Revisar log privado, conteos de jobs/failed_jobs y queue:failed; no exponer payloads ni destinatarios. Comprobar entrega con una venta normal autorizada por el cliente.
6. Rollback: desactivar Cron primero, conservar jobs/failed_jobs y las clases necesarias para jobs serializados, restaurar código y comprobar contrato JSON/API. No volver a SMTP síncrono ni restaurar DB a ciegas sobre ventas nuevas.

## Bloqueos concretos

1. Error SMTP 535: autenticación no validada/aceptada; no hay MAIL_DELIVERY_PASS.
2. Permisos de escritura de cache/logs y acceso DB por el usuario Cron pendientes.
3. Límite de ejecución y recursos del worker no probado en Neubox.
4. Respaldo verificable y comparación local/producción pendientes para una futura ventana.

Resultado: Cron/PHP CLI y boot Artisan productivo PASS; preflight global SMTP_AUTH_VALIDATION_PENDING. El worker productivo sigue apagado.
