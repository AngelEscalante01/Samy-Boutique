# Preparación de publicación · 1 de noviembre de 2026

La publicación pública todavía no se ha ejecutado. Se conserva el sistema de
origen en Neubox sin cambios de configuración, archivos de aplicación ni cron.
Los cambios locales sin commit se conservaron. El corte final y su autorización
se realizan después de esta preparación, mediante `RELEASE_CUTOVER_2026-11-01.md`.

## Direcciones definitivas

| Dirección | Uso |
| --- | --- |
| `https://samyboutique.com.mx/` | Catálogo público directamente, sin login |
| `https://www.samyboutique.com.mx/` | Redirección permanente al catálogo canónico |
| `https://sistema.samyboutique.com.mx/` | Sistema autenticado; API Android en `/api` |
| `https://imagenes.samyboutique.com.mx/` | Únicamente imágenes del bucket público |

El catálogo incluye títulos, descripciones, URL canónica, robots y sitemap XML.
El sistema entrega `X-Robots-Tag: noindex, nofollow`. Publicar el sitemap facilita
el rastreo; la aparición en buscadores depende del buscador.

## Paquete y aislamiento del VPS

Directorio privado: `/home/angel/samy-release-20261101`. PHP 8.2 FPM y Nginx
compilados por `docker/release/Dockerfile`; imágenes finales
`samy-release-app:20261101-r5` y `samy-release-gateway:20261101-r5`.
La imagen no contiene archivos `.env`, claves de servicio ni respaldos.

La vista privada usa Compose `samy-release`, base **sintética**
`samy_release_preview`, gateway en `127.0.0.1:18181`, sin workers ni scheduler y
sin rutas Traefik. Correo a log y push deshabilitado. Se accede por túnel SSH.

La configuración de producción está preparada en `.env.production.private`,
fuera del repositorio. Usa proyecto separado `samy-production`, base
`samy_production`, volúmenes propios y loopback `18182`. `SAMY_PUBLISH_ROUTING`
continúa en `false`; el entrypoint exige el marcador de autorización final
antes de arrancar servicios comerciales. El overlay público no se ha aplicado.
No se modificaron los servicios preexistentes del VPS ni el Traefik compartido.

`samy_restore` permanece fuera de aplicaciones, workers y ensayos. La limpieza
automática de fotos continúa deshabilitada.

## Evidencia comprobada

| Comprobación | Resultado |
| --- | --- |
| PHP 8.2 en imagen derivada del release, red deshabilitada | Resultado final y aserciones en la evidencia (suite ampliada con orientación y recuperación push) |
| HTTP real FPM/Nginx en VPS, datos sintéticos | **78 aprobadas**, login, permisos, módulos, venta/apartado/abono e idempotencia |
| Dominios por Host y HTTPS reenviado sobre loopback | **11 aprobadas**, catálogo en raíz, sistema/login, www, alias, robots, sitemap y separación de API |
| Regresión del sitemap | Pruebas con `short_open_tag=1` y HTTP PHP 8.2 correctas; declaración XML compatible y runtime con short tags deshabilitados |
| Acceso web de usuarios desactivados | Login bloqueado y sesión existente invalidada; dos regresiones aprobadas |
| Flutter | **137 aprobadas**; validación de HTTPS de producción comprobada también con el define activo |
| Android definitivo | APK/AAB firmados y verificados; APK instalada y abierta en Galaxy Tab A9+ con API definitiva visible |
| SMTP desde VPS | TLS con certificado válido y autenticación Resend aceptada; **0 correos enviados** |
| Firebase de producción | FCM `validate_only` HTTP 200; **0 avisos entregados** en esta comprobación |
| R2 producción | Objetos sintéticos público/privado con SHA-256 correcto; lectura privada firmada 200 y sin firma rechazada; HTTPS público 200 |
| Respaldo externo cifrado | Restic 0.19.1, `check --read-data`, recuperación y hashes correctos |
| Recuperación SQL del respaldo sintético | **58 tablas restauradas y 51 tablas comerciales comparadas, sin diferencias**; sin app ni worker conectados a la recuperación; cantidades de filas en la evidencia |
| Preparación de colas al corte | Ensayo sintético aprobado: payloads conservados, dispositivos antiguos inactivos, repetición sin efecto, registros nuevos intactos y filas financieras sin cambios |

La aceptación funcional previa de web y Android en VPS, recepción real de push,
offline, pérdida de respuesta e impresión simulada está en
`VPS_PRIVATE_ACCEPTANCE_2026-11-01.md` y en los documentos de Android. La prueba
real de Firebase del dominio/paquete definitivo se hace al activar HTTPS: el
nuevo origen y la nueva instalación requieren su propio registro/permiso.

## Traslado preparatorio de datos y fotos

Se obtuvieron copias privadas específicas de Samy; no se ejecutó código del
archivo descargado. La cuenta FTPS temporal quedó eliminada y su acceso fue
comprobado como rechazado. Se preservó su carpeta de respaldo.

| Archivo | SHA-256 |
| --- | --- |
| `neubox-current.sql.gz` | `11de666ee00019f5ddd9d5b7b2dc0d07d5836fadb440657eb768c077c5125117` |
| `samy-release-source-20261101.tar.gz` | `7c5326c35f822ee650b2d37e25296036ddd90be8f13d323e025f054dcab69916` |

El archivo completo mide 1,067,440,249 bytes. Sus copias local y VPS coinciden.
Se extrajeron solamente los archivos necesarios; los originales permanecen
conservados. El respaldo contiene 42 tablas y 151 columnas numéricas auditadas.

La restauración CLI en `samy_migration_20261101_c` convirtió diez tablas MyISAM
a InnoDB y aplicó migraciones dos veces (segunda sin pendientes). **Cero cambios
de cantidades de filas, sumas numéricas o valores de columnas originales**.
Esta copia comercial jamás se conectó a una web ni a workers ni se utilizó
para operaciones funcionales de prueba.

El `APP_KEY` del origen se conserva. Se comprobó en el código y entorno reales
del origen `APP_TIMEZONE=America/Mexico_City`; no se desplazan marcas de tiempo.
UTC se fija solamente en PHPUnit para que las pruebas no dependan del host.

La copia de fotos usa un manifiesto privado que valida esquema, respaldo y
buckets, una fuente congelada de solo lectura y comandos CLI. Cada original se
verifica por tamaño y SHA-256 antes de cambiar su referencia en la copia. Los
archivos de origen no se borran. El resultado final de originales y derivados
se registra en `docs/evidence/release-2026-11-01/verification.json`.

Resultado: **790 originales y 1,580 derivados WebP verificados**, sin imágenes
activas pendientes; logo también cargado y verificado. La conciliación de 41
tablas originales no detectó cambios inesperados en datos comerciales. Solo se
permiten metadatos de almacenamiento y `products.updated_at`: 517 productos
actualizaron esa marca para sincronizar las nuevas direcciones de sus imágenes.
Precios, existencias, ventas, pagos y fechas históricas se conservaron.

La auditoría EXIF detectó siete originales que requieren orientación. El generador
ahora respeta las ocho orientaciones JPEG; PHP EXIF queda declarado e instalado.
Se regeneraron y verificaron solo sus 14 derivados con direcciones nuevas,
conservando originales y copias anteriores. Repetir la reparación no cambió
referencias ni creó copias adicionales. Diez pruebas del generador verificaron
orientaciones, colores, dimensiones, conservación e idempotencia.

CORS del bucket público permite GET/HEAD desde los tres orígenes HTTPS de Samy
y el loopback privado 18181, sin credenciales ni escritura. Chrome descargó y
recuperó de caché una imagen sintética con el código real de precarga. El cliente
Dart real obtuvo la imagen con TLS validado y comprobó los orígenes permitidos;
un origen ajeno no recibe permiso CORS. El bucket privado continúa cerrado.

El respaldo de origen contiene **123 trabajos `SendSaleNotification` antiguos**
en la cola `sale-notifications` y tres dispositivos activos. No se procesaron.
Al corte, el helper preparado los conserva en una cola fuera de consumo,
inactiva los registros antiguos de push y exige iniciar sesión nuevamente.
El helper también fija la línea base de inventario y el ID histórico de apartados
para activar recordatorios de los nuevos sin una ráfaga de avisos antiguos.
Los avisos nuevos funcionan desde el nuevo origen/paquete. Los importes y el
historial comercial no se alteran por esta preparación.

El perfil `live` incluye un consumidor exclusivo de `push-notifications`, con
timeout suficiente para una tanda y cierre ordenado. Scheduler recupera filas
durables pendientes/reintentos vencidos cada minuto, con exclusión mutua y solo
si el transporte push está habilitado. Se prueba el rechazo temporal y posterior
éxito sin generar otro evento, con proveedor simulado y sin mensajes reales.
El respaldo coordina también este consumidor antes de capturar la base.

Estas copias son preparatorias: el origen sigue operando y habrá que obtener
un respaldo final consistente durante el corte.

## Respaldo automático preparado

`ops/release-backup.py` captura la base transaccional, archivos locales, código,
configuración y espejo verificado de medios R2. Precarga medios mientras la app
está disponible; después detiene ordenadamente los jobs y pausa brevemente las
mutaciones web de Samy durante la captura consistente. Reanuda los servicios
antes de subir/verificar el respaldo; incluye recuperación al fallar o reiniciar
el host. El repositorio
Restic cifrado se aloja en el bucket privado, bajo `samy/backups/restic`.
La contraseña de recuperación tiene resguardos privados fuera de la aplicación.

Unidad y timer instalados pero **deshabilitados hasta el corte**. Horario diario
preparado: 03:15 de Ciudad de México, con margen aleatorio de hasta cinco minutos.
No se configuró poda automática ni vencimiento/borrado de fotos.

## Android de distribución

Paquete `mx.com.samyboutique.app`, versión `1.0.0` / `2026110101`, Firebase
`samy-boutique`, API HTTPS del subdominio del sistema. Continúa siendo Flutter
nativo. Se conservan por separado PERF y sus datos.

APK de distribución directa y AAB preparados en
`../../samy-boutique-app/artifacts/release-2026-11-01/`. Firma y hashes en su
`release-manifest.json`; custodia/versionado en
`../../samy-boutique-app/RELEASE_IDENTITY_PENDING.md` (identidad ya resuelta).
No se ha publicado en Google Play.

## Lo que queda para salir

La conciliación de fotos y el respaldo recuperable con código incluido están
registrados en la evidencia. Solo queda ejecutar el corte autorizado: pausa
del origen, captura final, conciliación,
DNS/TLS, arranque y comprobación del nuevo origen. No cobrar en ambas bases.

La única prueba de hardware pendiente es la **impresora de tickets del cliente**.
58 y 80 mm se simularon; etiquetas y lector Bluetooth ya se probaron físicamente.
Su guía está en `CLIENT_TICKET_PRINTER_ACCEPTANCE_2026-11-01.md`.
