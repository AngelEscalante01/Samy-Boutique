# Samy Boutique — plan de despliegue Neubox

> **Actualización 2026-10-08:** la comparación final con producción y el paquete mínimo vigente están en [SAMY_FINAL_DEPLOY_PREFLIGHT_2026-10-08.md](SAMY_FINAL_DEPLOY_PREFLIGHT_2026-10-08.md). Este documento contiene hallazgos previos que fueron superados por la inspección directa de producción. El estado actual es `SAMY_DEPLOY_EXECUTION_BLOCKED_DB_BACKUP`; no reemplazar archivos productivos hasta resolver el dump SQL.

**Estado: HOSTING_EVIDENCE_PENDING — código local listo; PHP CLI de Cron y transporte de correo aún sin verificar**

La solución local conserva las notificaciones y ahora aplaza el job de correo 60 segundos después del commit. No se debe instalar en producción hasta verificar el PHP CLI real y los límites de ejecución de Cron. cPanel no ofrece una herramienta Terminal en esta cuenta; el PHP web seleccionado no demuestra qué ejecutable, versión o extensiones usa Cron. Se intentó una comprobación temporal de PHP CLI desde Cron, guardando la salida prevista en `/home/makedweb/.samy_queue_cli_diag_20261008.txt`, fuera de `public_html`; el archivo no apareció en File Manager. Las entradas temporales se eliminaron y cPanel volvió a mostrar **No Cron Jobs**. No se ejecutó Artisan ni se enviaron correos en producción.

## 1. Hallazgos confirmados

- El repositorio requiere PHP `^8.2` y Laravel Framework `11.48.0`.
- En cPanel, PHP Selector mostraba PHP web 8.2. phpMyAdmin mostraba su propio PHP 8.4.26; ese dato tampoco identifica el PHP CLI de Cron.
- La interfaz de Cron ofrece frecuencias de uno y cinco minutos y admite programar minuto, hora, día y mes. Esto no demuestra que el proceso CLI pueda ejecutarse o permanecer activo el tiempo necesario. Al terminar la prueba temporal, cPanel confirmó que no quedaban Cron Jobs.
- phpMyAdmin confirmó que `jobs` y `failed_jobs` existen y son InnoDB; ambas estaban vacías al consultar. También existen `notification_emails` y su migración aparece en `migrations`.
- La migración de Laravel para `jobs` / `failed_jobs` también aparece en `migrations`. No se observa una migración de cola pendiente.
- La configuración de aplicación local declara una conexión dedicada `sale_notifications`, driver `database`, tabla `jobs`, cola `sale-notifications`, `retry_after=120` y `after_commit=true`. La conexión se selecciona explícitamente en el despacho, así que el `QUEUE_CONNECTION=sync` general no la convierte en síncrona.
- La demora queda configurable con `SALE_NOTIFICATION_DELAY_SECONDS` (60 por defecto). La solicitud HTTP solo inserta el job diferido en la base de datos; no llama SMTP.
- Los límites visibles del plan eran 2 GB de memoria, 45 procesos de entrada, 75 procesos totales, 100% de CPU e I/O de 4 MB/s. La captura mostró uso actual en cero; no es una medición del momento de los cobros ni confirma límites específicos de Cron.

## 2. Bloqueos de preflight en Neubox

Estos puntos no se pudieron demostrar desde la interfaz disponible:

1. Ruta exacta, versión y arquitectura de PHP CLI usada por Cron.
2. Extensiones disponibles en ese PHP CLI, en particular `PDO`, `pdo_mysql` y `pcntl`. Laravel requiere además las extensiones de plataforma declaradas por Composer, incluyendo `ctype`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `session` y `tokenizer`.
3. Permisos de ese usuario CLI sobre `artisan`, `vendor`, `storage`, `storage/logs` y `bootstrap/cache`, y posibilidad de arrancar Artisan.
4. Si existe `flock`, dónde está y si el usuario Cron puede usarlo.
5. Duración máxima, límites LVE y política para procesos CLI de Cron, incluidos trabajos que siguen activos después de `--max-time`.

La comprobación temporal no produjo el archivo de salida en HOME en la fecha y minutos programados. El panel no presenta historial de ejecuciones del Cron; por ello, esto no demuestra que PHP CLI sea inexistente ni identifica por qué no apareció el archivo. No se volvió a crear una entrada recurrente. El menú de cPanel sí confirmó que las entradas temporales quedaron eliminadas.

El panel Cron ofrece la opción **Once Per Five Minutes**. Esa frecuencia limita la cadencia práctica: con demora de 60 segundos, la notificación normalmente quedaría disponible entre 1 y 6 minutos después de la venta, más el tiempo de ejecución del worker. No se comprobó que el plan permita procesos con duración suficiente.

En Laravel, el timeout de jobs requiere `pcntl`; el job declara 60 segundos y la conexión vuelve a poner disponible el trabajo a los 120 segundos. La relación es correcta (`timeout < retry_after`) si el runtime CLI implementa el timeout. El worker puede terminar un job que ya comenzó después de alcanzar `--max-time`, por lo que esa opción no protege por sí sola contra solapamientos. Consulta [Queues de Laravel 11](https://laravel.com/docs/11.x/queues) antes de autorizar el worker.

### SMTP 535

- Track Delivery de cPanel no mostró fallos registrados durante su ventana disponible de cuatro días. Esto no descarta un rechazo de autenticación SMTP saliente: Track Delivery refleja correo procesado por el MTA de cPanel, no necesariamente el diálogo AUTH de una aplicación Laravel con un servidor SMTP.
- En el repositorio local, `.env` usa `MAIL_MAILER=log`; no contiene una configuración SMTP que reproduzca el rechazo. No se leyó ni modificó la contraseña SMTP productiva.
- En cPanel, la configuración del buzón indica servidor SMTP `mail.makedweb.com`, puerto 465 con SSL/TLS, y autenticación requerida con el nombre completo del buzón como usuario y su contraseña de correo. El dato de acceso al panel es solo la configuración recomendada del buzón; no demuestra qué servidor/puerto está usando la aplicación actualmente.
- Laravel 11 construye el transporte usando `scheme`; la clave `encryption` que usaba esta configuración anterior no la consume `MailManager`. Se actualizó `config/mail.php` para admitir `MAIL_SCHEME` y `MAIL_TIMEOUT` (10 s). Para el servidor SMTP recomendado por cPanel, la configuración coherente es `MAIL_SCHEME=smtps`, host `mail.makedweb.com`, puerto `465`, usuario igual al buzón completo y `MAIL_FROM_ADDRESS` autorizado por ese buzón. Se conserva la contraseña actual; no se afirma que autentique.
- El código de error 535 confirma que el servidor SMTP rechazó AUTH, pero no identifica por sí solo si la causa es contraseña, usuario, cuenta restringida o política de autenticación. Si el servidor responde 535 aun con host/puerto/esquema/usuario conforme a cPanel, Neubox debe confirmar que la cuenta permite SMTP AUTH desde el hosting y que la contraseña vigente corresponde a esa cuenta. No habilitar pruebas SMTP productivas hasta tener diagnóstico.

Para cerrar estos puntos, el proveedor o una Terminal/SSH autorizada debe ejecutar desde `/home/makedweb/public_html/samy-boutique` este diagnóstico de solo lectura. No ejecutar `queue:work`, pruebas SMTP, comandos de migración ni comandos que procesen jobs:

```sh
printf 'PHP='; command -v php
php -v | head -n 1
php -m | sort
printf 'FLOCK='; command -v flock || true
printf 'TIMEOUT='; command -v timeout || true
id
for f in artisan vendor/autoload.php; do test -r "$f" && echo "readable: $f" || echo "not-readable: $f"; done
for d in storage storage/logs bootstrap/cache; do test -w "$d" && echo "writable: $d" || echo "not-writable: $d"; done
php artisan --version
ulimit -a
```

`ulimit` refleja la sesión donde se ejecuta; Neubox también debe confirmar por escrito límites propios de Cron/LVE, duración máxima y si `pcntl` está soportado. `php artisan --version` comprueba arranque básico, no procesa la cola.

## 3. Revisión de la corrección

- `CreateSaleAction::execute()` termina `DB::transaction()` antes de llamar a `NotificationService::notifySale()`. Si existe una transacción exterior, `DB::afterCommit()` difiere el despacho hasta que se confirme.
- El servicio despacha un trabajo por destinatario activo usando Laravel `dispatch()` con `ShouldBeUnique`; el despacho único se mantiene mientras el trabajo está pendiente. Los errores al registrar la cola se capturan y registran con IDs, sin convertir la venta confirmada en error HTTP.
- `SendSaleNotification` es el único lugar encontrado en `app/` que ejecuta `Mail::to(...)->send(...)`; ocurre dentro de `handle()` del job. No encontré otros envíos de correo síncronos en el camino de venta web/API local.
- El job usa tres intentos, backoff `[60, 300]`, timeout de 60 segundos y registro limitado a IDs al agotar intentos. Las excepciones de SMTP se propagan para que Laravel reintente.
- La prueba confirma contrato JSON actual, venta e inventario conservados, rollback sin job, fallo de cola sin error HTTP, envío solo al ejecutar el job, deduplicación mientras está pendiente y el mismo flujo compartido por web y móvil.
- La cola se registra después del commit, no en la misma transacción que la venta. Si el insert del job falla, la venta permanece válida y el log permite recuperación manual. Ese caso no es una outbox transaccional.
- La unicidad usa el cache configurado por Laravel. La prueba local verifica la deduplicación dentro de un proceso de test; todavía falta confirmar que el cache/locks y los permisos del runtime CLI funcionen entre procesos en Neubox.
- No se implementa idempotencia de ventas por clave de operación. Un reintento del cliente tras un timeout aún puede crear una venta duplicada; queda pendiente prioritario separado.

## 4. Base de datos y migraciones

| Elemento | Producción observada | Acción prevista |
|---|---|---|
| `jobs` | Existe, InnoDB, vacía al revisar | Reutilizar |
| `failed_jobs` | Existe, InnoDB, vacía al revisar | Reutilizar |
| `notification_emails` | Existe; su migración figura aplicada | Reutilizar |
| `job_batches` | La migración de Laravel la define; su presencia no se verificó en phpMyAdmin | No se usa en este flujo; confirmar solo si se habilitan batches |

No se propone migración productiva nueva ni ejecutar `migrate`. Antes del cambio, comparar `migrations` y la estructura de esas tablas con el respaldo. No ejecutar migraciones solo para forzar una comprobación.

## 5. Worker y Cron propuestos

La interfaz cPanel permite frecuencia de cinco minutos. Solo usar el siguiente comando **después** de confirmar la ruta de PHP CLI, `flock`, permisos y límites. La ruta de PHP es un marcador hasta verificarla; `/usr/local/bin/php` aparece como ejemplo genérico del panel, no está confirmada para esta cuenta.

```cron
*/5 * * * * /usr/bin/flock -n /home/makedweb/.sale-notifications.lock <PHP_CLI_CONFIRMADO> /home/makedweb/public_html/samy-boutique/artisan queue:work sale_notifications --queue=sale-notifications --stop-when-empty --max-time=180 --timeout=60 --tries=3 --sleep=1 >> /home/makedweb/public_html/samy-boutique/storage/logs/queue-sale-notifications.log 2>&1
```

- `--queue=sale-notifications` limita el worker a esa cola; `sale_notifications` selecciona la conexión database dedicada.
- `--stop-when-empty` deja que Cron termine el proceso cuando no quedan trabajos.
- `--max-time=180` limita la vida prevista del worker entre trabajos; un job activo puede terminar después de los 180 segundos. El máximo es candidato y requiere confirmación de Neubox.
- El job usa timeout de 60 segundos y `retry_after=120`; el tiempo máximo que permita el hosting debe ser superior al tiempo real de un job. Confirmar el timeout de SMTP/hosting porque un socket bloqueado puede no obedecer el timeout de cola.
- Con Cron cada cinco minutos, `flock` evita arrancar otro worker si la ejecución anterior aún conserva el lock. Verificar también la ruta de `flock`; no se ha demostrado que esté instalado.
- Si `flock` no existe, no activar Cron hasta que Neubox confirme un mecanismo de exclusión compatible o se prepare y pruebe un wrapper con lock atómico y recuperación segura de locks obsoletos. No confiar solo en `--max-time` para excluir concurrencia.
- Una venta registrada justo antes de la siguiente ejecución puede esperar casi cinco minutos antes de que Cron vea el job, además de la demora configurada de 60 segundos. Cada ejecución acepta hasta 180 segundos de trabajo antes de detenerse normalmente. No se ha confirmado que el hosting permita ese proceso ni que no lo termine por CPU, memoria, procesos o límite de tiempo.
- Los reintentos no son ilimitados; al terminar tres intentos, Laravel registra el job en `failed_jobs`. Inspección propuesta: `php artisan queue:failed`; reintento manual solo después de revisar el destinatario y la venta.

## 6. Archivos del despliegue

**Cambios de remediación SMTP/cola:**

- Modificado: `app/Actions/Sales/CreateSaleAction.php`
- Modificado: `config/queue.php` (demora configurable de 60 s después del commit)
- Modificados: `config/mail.php`, `.env.example` (`MAIL_SCHEME`, timeout SMTP de 10 s; sin alterar `.env`, contraseña ni `APP_KEY`)
- Nuevos: `app/Services/NotificationService.php`, `app/Jobs/SendSaleNotification.php`, `app/Models/NotificationEmail.php`, `app/Mail/SaleNotificationMail.php`
- Nuevo: `resources/views/emails/sale_notification.blade.php`
- Nuevo en la rama local: `database/migrations/2026_05_05_000001_create_notification_emails_table.php` (la tabla y migración ya existen en producción; no ejecutar allí)
- QA local: `tests/Feature/SaleNotificationQueueTest.php`, `tests/TestCase.php` (registra `CONCAT` para compatibilidad de SQLite de Windows durante pruebas; no cambia producción)

**Cambios de rendimiento preexistentes, separados de esta remediación:** `app/Http/Controllers/Api/BusinessApiController.php`, `bootstrap/app.php` y `app/Http/Middleware/SalePerfAudit.php`. Mantenerlos en la rama local; decidir explícitamente si pertenecen al paquete antes de desplegar. No incluirlos accidentalmente ni descartarlos.

No hay cambios Flutter.

## 7. Simulación y QA local

Ejecución local de esta entrega:

- PHPUnit 10.5.63 sobre PHP 8.2.12: 41 aprobadas, 284 aserciones y 1 benchmark optativo omitido. Se ejecutó el runner directamente con configuración temporal porque el comando Artisan de Windows no puede usar el directorio UNC y WSL no arrancó; la suite usa la misma configuración de prueba y el shim SQLite de `tests/TestCase.php`.
- El caso `SaleNotificationQueueTest`: 8 casos, 48 aserciones, 1 omitido; todos los casos ejecutados aprobaron.
- Benchmark explícito: 10 muestras por escenario, 20 jobs para 10 ventas. El escenario síncrono agrega 20 s sintéticos (dos esperas SMTP fallidas asumidas); el fake de SMTP lento fallido se ejecutó después de medir las solicitudes de venta. Sin conexión de red ni SMTP real.
- Medición en PHP Windows leyendo el repo por UNC: síncrono modelado mediana 20,009.69 ms, p95 20,955.60 ms; cola mediana 18.75 ms, p95 2,603.01 ms. El p95 de la cola está contaminado por la latencia variable del share UNC y no representa una medición de producción. El intento SMTP falso midió 987.57 ms por el mismo entorno, aunque el fake espera 100 ms.
- `vendor/bin/pint --test` completo: 169 archivos inspeccionados, 36 problemas de estilo en archivos preexistentes (incluye controladores/rutas, caches y otras clases no modificadas en esta tarea). Los 4 archivos PHP de esta entrega pasaron Pint.
- `git diff --check`: aprobado.

En este turno se reintentó `php artisan test`. La ejecución desde WSL terminó por timeout del servicio WSL (`Wsl/Service/0x8007274c`); la ruta alterna Windows abrió PHPUnit, pero no pudo cargar `vendor/autoload.php` desde el volumen WSL. Ese intento generó un archivo temporal local `NUL`, que se retiró. No cambió el código de la aplicación durante estos intentos. Los resultados aprobados de la ejecución anterior siguen siendo la última suite completa válida.

No se ejecutó worker Laravel real contra Neubox ni se entregó un correo SMTP real. Track Delivery no tenía registros en su retención de cuatro días. La prueba Flutter de impresora desconectada no se ejecutó porque Flutter no está disponible en este host; la revisión del flujo confirma que `POST /api/sales` devuelve la venta antes de la pantalla de confirmación, y que la impresión ocurre después y captura errores. No hay acoplamiento entre impresión y cola del servidor.

No se ejecutó aquí un worker real de Laravel ni se verificaron estos puntos con un runtime CLI Linux en este turno: aislamiento del worker ante una cola distinta, concurrencia entre procesos, interrupción/reanudación real y registro real en `failed_jobs`. Las pruebas de job y retry son de integración/mocks locales, no de SMTP real ni del runtime de Neubox.

## 8. Procedimiento de despliegue (cuando se autorice)

1. Guardar la versión productiva actual (referencia/fecha) y preparar respaldo descargable de archivos y base de datos.
2. Confirmar PHP CLI >= 8.2, extensiones Composer y `pdo_mysql`; confirmar `pcntl` para el timeout, `flock`, escritura en logs/cache y límites Cron.
3. Comparar tablas y migraciones con la sección 4. No ejecutar migraciones si siguen presentes las tablas y registros observados.
4. Subir el paquete de remediación revisado, sin sobrescribir `.env` ni modificar credenciales. Asegurar que `vendor` corresponde al `composer.lock` de Laravel 11.48 y no ejecutar `composer update` en producción.
5. Confirmar `config/queue.php` desplegado y que la conexión `sale_notifications` resuelve a database con la cola dedicada aun cuando la conexión default sea sync.
6. Crear Cron solo después de que exista el código y el comando esté verificado. Redirigir salida a un log con permisos adecuados. Mantenerlo en una sola instancia protegida por lock.
7. Verificar `/up` HTTP 200, login y rutas API de lectura, ausencia de errores Laravel nuevos, conectividad CLI básica, conteo de jobs por cola y Cron/logs. No crear ventas ni enviar una notificación productiva desde Codex.
8. Después de una venta normal autorizada por el cliente, comparar nueva traza PERF, tiempo HTTP, llegada de trabajos, logs y correo recibido; revisar `queue:failed` sin exponer datos personales.

## 9. Rollback

- Pausar el Cron antes de cambiar clases que puedan estar serializadas en jobs pendientes.
- No borrar ni truncar `jobs` o `failed_jobs`; preservar payloads hasta que una versión compatible los procese.
- Mantener temporalmente `SendSaleNotification`, el mailable y sus dependencias hasta vaciar o recuperar la cola.
- Si hay que revertir otras partes del código, conservar el camino asíncrono o aceptar explícitamente que volver al código síncrono reintroduce la latencia del cobro.
- Restaurar archivos desde el respaldo, comprobar el contrato JSON y revisar los jobs pendientes antes de reactivar el Cron.
- Los jobs en `failed_jobs` se reintentan de forma selectiva después de confirmar que el fallo fue transitorio; un SMTP ambiguo puede haber aceptado un correo antes del error y producir un duplicado en reintento.

## 10. Alternativa condicionada: Cron HTTPS protegido

No se implementa esta opción mientras no se demuestre que el PHP CLI es inviable. Si cPanel confirma que Cron no puede ejecutar PHP CLI, preparar un endpoint interno aparte de `POST /api/sales`, atendido solo por `POST` autenticado mediante un secreto independiente enviado en cabecera (nunca en URL), validado con `hash_equals`, sin registrar `Authorization` ni cuerpos. Aplicar rate limit, respuesta genérica y lote máximo pequeño; adquirir un bloqueo atómico de base de datos con expiración/recuperación, liberar el bloqueo al terminar y procesar exclusivamente `sale_notifications` / `sale-notifications`. El trabajo debe ejecutarse dentro de un límite corto y compatible con PHP web; el endpoint no debe invocar un worker indefinido ni ofrecer datos de jobs a visitantes. El Cron HTTPS usaría `curl` con cabecera secreta protegida en configuración del Cron, `--max-time` y salida a log privado. Antes de producción, probar el endpoint con SMTP falso, fallo y concurrencia; no procesar correo en el cobro.

Riesgos: depende del límite de duración y memoria de PHP web, consume un proceso de entrada durante SMTP y requiere proteger el secreto persistente en cPanel; una respuesta HTTP interrumpida puede dejar trabajo reclamado hasta que expire el lock. No está implementada, probada ni seleccionada porque no se ha demostrado que CLI sea inviable.

## 11. Alternativas si Neubox no ofrece un worker fiable

| Opción | Ventajas | Riesgos / costos |
|---|---|---|
| Database queue + Cron cada cinco minutos | Menor cambio productivo; reutiliza `jobs`; no agrega servicio externo | Retraso de 1–6 minutos más el tiempo de envío; depende del PHP CLI, `flock`, límites de Cron y acceso a MySQL. Si Cron termina o se solapan procesos puede acumularse cola. Costo incremental: normalmente incluido en el hosting, sujeto al plan. |
| Worker Laravel pequeño en VPS/PaaS, conectado a la base de datos actual | Worker persistente, menor latencia y visibilidad operativa; mantiene el job Laravel actual | Pago recurrente del servidor/servicio; habilitar conexión remota MySQL y lista de IPs añade superficie de acceso; hay que asegurar TLS, credenciales de mínimo privilegio, monitoreo y continuidad. Confirmar precio del proveedor/plan elegido antes de estimar. |
| Cola gestionada SQS y consumidor fuera de Neubox | Servicio administrado, reintentos y escalado; retardo de mensaje de 60 s cabe en el límite de SQS | Requiere cambiar backend de cola y credenciales IAM, instalar/verificar AWS SDK y construir un consumidor Laravel en Lambda u otro worker. SQS factura por solicitudes; AWS publica 1 millón de solicitudes gratuitas al mes y Lambda publica un nivel gratuito de 1 millón de solicitudes y 400,000 GB-s, con cargos regionales/uso al excederlo. No equivale a tener un worker ya operativo. [Precios SQS](https://aws.amazon.com/sqs/pricing/), [precios Lambda](https://aws.amazon.com/lambda/pricing/), [límite de demora SQS](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-delay-queues.html). |

Recomendación: primero probar el Cron de cinco minutos bajo el plan contratado. Si Neubox no confirma CLI/`pcntl`/`flock` y límites compatibles, preferir un worker administrado externo antes que `dispatchAfterResponse()`. No activar producción sin resolver esta elección y el 535 de SMTP.

## 12. Condiciones para activar el worker productivo

Activar el worker productivo solo cuando estén documentados:

- ruta y versión exactas de PHP CLI, extensiones requeridas y arranque de Artisan;
- permisos de aplicación, storage, cache y base de datos;
- disponibilidad de `flock` o alternativa probada;
- límites/terminación de procesos Cron suficientes para el timeout de 60 segundos;
- prueba local de worker limitado a la cola, fallo/reintento, failed_jobs, exclusión de concurrencia y reanudación.

Hasta entonces, el código local mantiene aprobado el flujo diferido, pero la solución operativa de Neubox y el despliegue siguen pendientes de evidencia técnica del hosting. No se declaró `NEUBOX_QUEUE_SOLUTION_READY` porque el diagnóstico CLI no produjo datos verificables.

## Addendum — preflight Neubox 2026-10-08

La evidencia previa de que faltaban datos verificables de Cron/PHP CLI quedó superada por [NEUBOX_PREFLIGHT_2026-10-08.md](NEUBOX_PREFLIGHT_2026-10-08.md). Se confirmó el Artisan productivo real en /home/makedweb/public_html/samy-boutique, Laravel 11.48.0 por Cron cada cinco minutos y PHP CLI 8.2.34 con pcntl, PDO y pdo_mysql. El Cron temporal ya se retiró; cPanel muestra No Cron Jobs.

Suite completa: 42 aprobadas (302 aserciones), 1 benchmark optativo ejecutado aparte; Pint focalizado y git diff --check aprobados. El benchmark es simulado y no demuestra tiempos productivos. El worker acotado está preparado, pero no instalado.

Estado vigente: SMTP_AUTH_VALIDATION_PENDING, no NEUBOX_DEPLOY_READY: SMTP conserva el rechazo 535 y faltan comprobar permisos de escritura de cache/logs y límites reales del worker. No se envió correo, no se modificó producción y no hubo commit/push.
