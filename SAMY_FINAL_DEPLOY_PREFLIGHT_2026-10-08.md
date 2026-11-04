# Samy Boutique — preflight final de despliegue

**Estado: `SAMY_DEPLOY_READY`**
Rama local: `codex/perf-sale-api`
Producción: `/home/makedweb/public_html/samy-boutique`

No se modificaron archivos Laravel, configuración, base de datos ni Cron en producción. No hubo ventas ni correo de prueba. El único cambio productivo durante esta preparación fue crear la carpeta privada para guardar respaldos y copiar dentro las dos versiones de archivo que se sustituirían.

## Alcance mínimo comparado con producción

La inspección de producción cambió el paquete previsto anteriormente. Producción ya tiene `CreateSaleAction` llamando a `NotificationService::notifySale()` después de cerrar `DB::transaction()`. También tiene `NotificationEmail`, `SaleNotificationMail` y su vista. No hace falta sustituir esos archivos ni ejecutar migraciones.

Para dejar `POST /api/sales` sin espera de SMTP bastaría desplegar:

1. **Reemplazar** `app/Services/NotificationService.php`. El archivo preparado despacha el job de venta después del commit y conserva los avisos síncronos existentes de creación de apartados y abonos.
2. **Añadir** `app/Jobs/SendSaleNotification.php`. Ejecuta el mail únicamente cuando un worker procese el job; aplica tres intentos, backoff limitado y unicidad por venta/destinatario.
3. **Reemplazar** `config/queue.php`. Añade la conexión dedicada `sale_notifications` con driver `database`, tabla `jobs`, cola `sale-notifications` y retraso de 60 segundos. El despacho selecciona esa conexión de forma explícita, incluso si `QUEUE_CONNECTION=sync`.

No incluir en el paquete productivo: `CreateSaleAction.php`, `.env`, `APP_KEY`, `config/mail.php`, migraciones, middleware o instrumentación PERF, archivos de diagnóstico, pruebas, cambios de Android ni el resto de archivos de la rama.

### Versiones productivas identificadas

- `app/Services/NotificationService.php`: 3.66 KB, última modificación 5 may 2026, 5:35 p. m.; copia en HOME verificada como 3.66 KB, 8 oct 2026, 4:06 p. m.
- `config/queue.php`: 3.72 KB, última modificación 12 mar 2024, 6:52 a. m.; copia en HOME verificada como 3.72 KB, 8 oct 2026, 4:07 p. m.
- `app/Jobs/` existe y estaba vacío. `SendSaleNotification.php` es un archivo nuevo.
- La carpeta de copias es `/home/makedweb/samy_sale_predeploy_20261008`, fuera de `public_html`, y se verificó con permiso `0700`. Los dos archivos están dentro; sus permisos son `0666`, pero solo son accesibles a través de la carpeta `0700` del propietario. Se recomienda ajustar cada archivo a `0600` antes de conservar el respaldo a largo plazo.

La copia local no tiene hashes calculados, por lo que identidad se verificó visualmente por nombre, tamaño y fecha en cPanel. No restaurar el código desde un respaldo cuya integridad no se haya vuelto a verificar antes de una ventana.

## Respaldo de base de datos: exportación manual verificada

El usuario completó manualmente la exportación desde phpMyAdmin. El respaldo quedó en `C:\Users\Angel\Downloads\makedweb_samy_boutique (1).sql` (fuera del repositorio). La página Backup de cPanel no muestra respaldos automáticos disponibles; sí ofrece descargas manuales por base de datos.

Verificaciones del archivo:

- Tamaño: **654,552 bytes**, modificado el **8 oct 2026, 4:20:30 p. m.**; el encabezado de phpMyAdmin indica generación a las **4:20:28 p. m.**
- SHA-256: `68CEB6C6F69721A8F54E23EDB65B0358813B402045586AFA1720AB68D0A7EBF6`.
- El encabezado identifica phpMyAdmin 5.2.3 y MariaDB 10.6.28. La exportación se inició desde la base seleccionada `makedweb_samy_boutique`.
- Se encontraron **34 definiciones `CREATE TABLE`** y **33 sentencias `INSERT`**. Los 34 nombres corresponden a las tablas que phpMyAdmin mostraba para esa base; están incluidas `jobs`, `failed_jobs`, `notification_emails`, `sales`, `sale_items`, `sale_payments` y `migrations`.
- No aparecen mensajes de error/truncamiento; el archivo termina con la instrucción estándar de phpMyAdmin que restaura `COLLATION_CONNECTION`. La descarga es SQL sin gzip, así que no aplica una prueba gzip.
- No se restauró sobre producción. La restauración aislada no se completó: Docker/MariaDB local inició y cayó durante la inicialización del servidor de prueba y el cliente encontró el socket no disponible. El contenedor temporal fue eliminado; no se concluye que el SQL esté defectuoso.

La validación estructural y el checksum permiten conservar esta exportación como respaldo predespliegue. La falta de restauración aislada queda como limitación. Como el despliegue mínimo no cambia el esquema, restaurar la base no debe formar parte del rollback normal; mantener este dump local y protegido.

En la sesión limpia de Chrome, phpMyAdmin también devolvió `ERR_BLOCKED_BY_CLIENT` al intentar descargar. El usuario resolvió la descarga manualmente. Dos Cron de diagnóstico se retiraron; cPanel muestra `No Cron Jobs`. No se generó archivo temporal de sondeo en la carpeta privada.

## Compatibilidad y seguridad de la ruta

- Evidencia previa del preflight: Laravel 11.48.0, PHP CLI 8.2.34 (`/usr/local/bin/ea-php82`), `PDO`, `pdo_mysql` y `pcntl` disponibles. Cron cada cinco minutos ya se demostró funcional. No hay Cron configurado ahora.
- En la inspección actual, phpMyAdmin observó las tablas `jobs`, `failed_jobs` y `notification_emails`; `jobs` / `failed_jobs` estaban vacías. La tabla `notification_emails` tenía dos destinatarios configurados. Su migración y las migraciones de queue estaban registradas en la evidencia previa. No es necesaria una migración para este paquete.
- El driver `sync` general no afecta el despacho, porque el job fija `sale_notifications` explícitamente. La conexión nueva usa la tabla persistente `jobs`.
- `CreateSaleAction` ya termina la transacción antes de notificar. `NotificationService` registra la cola con `DB::afterCommit()` y captura errores de despacho; así, si el insert del job falla después del commit, la venta ya confirmada no se transforma en una respuesta de error ambigua. Puede perderse el correo de esa venta y se registra un error de aplicación.
- El mail se construye en `SendSaleNotification::handle()`. El POST de venta no ejecuta SMTP ni depende de Bluetooth. Los contratos JSON de web y Android no cambian.
- `config_cached=false` fue el último resultado de la comprobación de producción. Al momento del despliegue se debe volver a comprobar. Si continúa sin caché, no hace falta regenerarla. Si está activa, después del reemplazo ejecutar `php artisan config:cache` y comprobar que termina sin errores; no modificar `.env`.
- SMTP sigue pendiente; el último resultado autorizado fue `SMTP_DIAGNOSTIC_BLOCKED`. No desplegar un worker ni ejecutar jobs mientras el SMTP no esté validado. Las notificaciones de apartados y abonos siguen siendo síncronas como hoy; esta corrección quita la espera SMTP del cobro de venta.

## QA local del turno

- `php artisan test`: **42 aprobadas, 302 aserciones, 1 omitida** (benchmark opcional).
- `RUN_SALE_LATENCY_BENCHMARK=1 php artisan test --filter=test_local_legacy_vs_queued_latency_benchmark`: aprobado. Simula 10 muestras de cada flujo y dos esperas SMTP fallidas de 10 s por venta en el caso legado, sin dormir 20 s ni conectarse a SMTP. Resultado: HTTP legado mediana 20,006 ms; cola mediana 7.43 ms, p95 48.77 ms; 20 jobs pendientes de prueba. Esto es simulación local, no medición de Neubox.
- `vendor/bin/pint --test` en los cinco archivos afectados: aprobado.
- Pint de todo el repositorio: 34 problemas de estilo en archivos existentes/ajenos al paquete; no se reformatearon.
- `git diff --check`: aprobado. Sin commit ni push.

Hash SHA-256 del paquete actual preparado para despliegue:

- `app/Services/NotificationService.php`: `A080C0066DDADD04567AA44221CC1F0927B99C11C2ED85C0C53738423E984605`
- `config/queue.php`: `EAA62D448338A16A089E5EB1EE0BE9DC54315C212F6517D2E284898C47EE223D`
- `app/Jobs/SendSaleNotification.php`: `287803E953E331D1E85E6CCC8CB7E58260A829F08387D35DE0EB0F9ED004A37A`

Estos hashes identifican los archivos locales preparados, no los archivos que están actualmente en producción. La preinspección productiva y la copia de predespliegue de los dos archivos a reemplazar constan arriba; antes de la ventana aún se debe verificar que producción no haya cambiado desde esa inspección.

## Cola sin worker y monitoreo

No activar Cron ahora. Los jobs quedarán almacenados en `jobs` con cola `sale-notifications` y `available_at` retrasado. No ejecutar `queue:work`, `queue:retry` o `queue:clear`; no truncar `jobs` ni `failed_jobs`.

Consultas de solo lectura para correr en phpMyAdmin (no seleccionar ni mostrar `payload`):

```sql
SELECT COUNT(*) AS pending,
       MIN(created_at) AS oldest_created_epoch,
       MIN(available_at) AS earliest_available_epoch
FROM jobs
WHERE queue = 'sale-notifications';

SELECT COUNT(*) AS failed_total,
       MIN(failed_at) AS oldest_failed_at
FROM failed_jobs;
```

Calcular la antigüedad como `UNIX_TIMESTAMP(UTC_TIMESTAMP()) - oldest_created_epoch`; los timestamps de `jobs` son epoch Unix. Revisar conteos en la ventana de cada despliegue/operación y llevar una serie temporal manual.

- Aviso operativo: 500 jobs pendientes o el más antiguo con 24 horas.
- Escalación urgente: 1,000 jobs pendientes o el más antiguo con 7 días.
- No borrar automáticamente. Al alcanzar límites, informar al responsable; los cobros siguen independizados del correo. Tras reparar SMTP, activar el worker en una ventana separada con lote pequeño, revisar `failed_jobs`, y comprobar duplicados antes de reintentar.

La tabla `jobs` no contiene un contador de edad calculado y el panel no mostró un conteo actual por cola; su estado cero anterior es un snapshot. Se requiere leer el conteo inmediatamente antes del despliegue y monitorear el crecimiento después.

## Procedimiento propuesto para una ventana autorizada

1. Acordar una ventana de bajo uso con el cliente; no se estableció una fecha/hora en esta preparación.
2. Antes de tocar código, exportar la base y verificar el archivo SQL, verificar la carpeta/copia de los dos archivos productivos y tomar copia del archivo nuevo ausente (`app/Jobs/SendSaleNotification.php` no tiene preimagen).
3. Registrar conteo inicial y antigüedad de la cola y de `failed_jobs`; no leer payloads.
4. Confirmar de nuevo versión Laravel/PHP, `config_cached`, rutas de los archivos y que no haya cambios productivos nuevos desde la inspección. Comparar los tres archivos exactos.
5. Copiar primero el Job nuevo a `app/Jobs/SendSaleNotification.php`; después reemplazar solo `NotificationService.php` y `config/queue.php`. No ejecutar Artisan de venta, worker, SMTP, migración ni seed.
6. Si la configuración estaba cacheada, ejecutar `php artisan config:cache`; de lo contrario dejar caché intacta. No usar `optimize:clear` ni borrar cachés amplias.
7. Verificar `/up` HTTP 200, disponibilidad de login, `/api/me` devuelve 401 sin autenticación, errores recientes y que los archivos tengan sintaxis válida con PHP CLI. Consultar configuración `sale_notifications` sin mostrar valores secretos. No hacer POST de venta productivo.
8. Registrar de nuevo cola y `failed_jobs`; comprobar que no se activó ningún Cron. Vigilar errores Laravel. La inserción real de un job requiere una venta normal y se comprobará con la siguiente venta habitual del cliente/APK PERF; no generar venta sintética.
9. Medir la siguiente venta normal con APK PERF y cotejar el tiempo Android con los logs. Solo entonces evaluar el objetivo de latencia.

### Rollback

1. Mantener el worker apagado (no hay Cron para desactivar).
2. Restaurar `NotificationService.php` y `config/queue.php` de la carpeta de respaldo. Quitar el Job nuevo solo después de confirmar que no quedan trabajos que hagan referencia a esa clase; con trabajos pendientes, mantener temporalmente código compatible.
3. Si se creó caché de configuración, reconstruirla con la versión restaurada. Verificar `/up`, login y API.
4. No restaurar la base durante rollback del código, salvo una decisión separada ante daño de datos. No vaciar cola. Restaurar la versión síncrona volverá a exponer la espera SMTP de ~21 s al registrar venta.

## Bloqueo para autorización

El paquete de código mínimo y el dump local están preparados. El dump tiene verificación estructural y SHA-256; no se completó una restauración aislada. Antes de desplegar todavía se debe comprobar que no haya cambios productivos posteriores y acordar una ventana de bajo uso. No se reemplazaron archivos productivos y no se activó el worker.

El despliegue sigue detenido hasta que el usuario dé la autorización explícita `AUTORIZO DEPLOY SAMY`.
