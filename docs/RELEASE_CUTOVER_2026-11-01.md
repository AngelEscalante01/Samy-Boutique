# Corte final y recuperación · Samy Boutique

Este procedimiento está preparado, no ejecutado. Requiere autorización expresa
del corte final. No habilitar DNS/rutas ni crear `cutover-approved` durante la
preparación. VPS confirmado: `2.25.126.224`, usuario `angel`.

## 1. Congelar el origen y capturar un conjunto consistente

1. Confirmar que terminaron los cobros y pendientes de los dispositivos del
   origen. Los carritos pueden conservarse, pero no debe quedar una operación
   comercial con respuesta incierta antes de cambiar de base.
2. Poner **solo Samy** de Neubox en mantenimiento y pausar su cron/workers.
   Conservar antes una copia de la configuración de cron. No modificar otros
   sistemas del alojamiento o del VPS. Registrar la hora de congelación.
3. Exportar únicamente `makedweb_samy_boutique` y los archivos privados de
   `/home/makedweb/public_html/samy-boutique`, con la fuente ya detenida. Hay
   tablas MyISAM en origen: una captura sin pausa no garantiza consistencia.
4. Para el archivo grande, recrear únicamente la cuenta temporal restringida
   `samymove20261101@samy-boutique.makedweb.com`, carpeta
   `/home/makedweb/samy-release-transfer-20261101`, FTPS con validación TLS.
   Transferir al equipo y al VPS, comparar SHA-256 en ambos y retirar la cuenta
   sin eliminar la carpeta. No reutilizar contraseñas de tareas anteriores.
5. Guardar los archivos finales con nombre nuevo y manifiesto. No sobrescribir
   la captura preparatoria ni restaurar sobre `samy_restore`.

## 2. Restaurar, migrar y conciliar antes de abrir

Trabajar en `/home/angel/samy-release-20261101`. Iniciar únicamente la base del
proyecto **nuevo** `samy-production` con `.env.production.private`; no ejecutar
app, worker o scheduler todavía. El puerto de publicación privado es `18182`;
la vista sintética `18181` puede permanecer abierta sin colisiones.

Restaurar el SQL final en `samy_production` con credenciales propias. Auditar
filas, columnas originales y sumas antes de migrar, convertir MyISAM a InnoDB,
aplicar migraciones y volver a comparar. Repetir migraciones debe informar que
no hay pendientes. Un fallo de conciliación detiene el corte.

Usar la imagen `samy-release-app:20261101-r5` con entrypoint CLI y entorno
`migration`: sin servidor, correo a log y push deshabilitado. Ajustar las redes
de `compose.migration.yaml` a las redes propias de `samy-production`, el esquema
al destino final y el manifiesto de aprobación al SHA-256 del SQL final.
Montar los archivos públicos finales congelados como solo lectura. El guard
`ProductionMediaMigrationGuard` rechaza `samy_restore` y destinos ajenos.

Ejecutar por lotes reanudables:

```sh
php artisan images:migrate-r2 --execute --limit=1000
php artisan images:migrate-r2 --verify-existing --limit=1000
php artisan images:backfill-derivatives --execute --limit=1000
php artisan media:migrate-brand-logo --execute
```

El generador de esta imagen respeta EXIF automáticamente, incluidos originales
de teléfono girados. Para una copia antigua ya procesada, se puede inventariar
con `ops/scan-migration-orientation.php` y reparar solo los IDs afectados con
`images:backfill-derivatives --execute --refresh-orientation --id=ID`. Conserva
originales y objetos anteriores; la repetición no cambia referencias corregidas.

Si hay más de 1,000 imágenes, continuar con `--after-id` y tratar los IDs que
fallaron explícitamente. Verificar originales, derivados, logo y referencias;
conciliar otra vez datos comerciales sin permitir cambios ajenos a metadatos
de medios/migraciones. Mantener los originales en origen y en respaldo.

Conservar APP_KEY y timezone del origen, correo Resend del dominio y Firebase
de producción. Mantener cookies seguras y limitadas al host, sin
`MAIL_DEVELOPMENT_TO`. No sembrar usuarios, ventas ni productos QA en producción.

## 3. Evitar envíos históricos y preparar respaldo

Con origen detenido y conciliación aprobada, registrar la autorización en
`release-state/cutover-approved` y los hashes finales en el estado privado.
Antes de arrancar servicios comerciales:

```sh
python3 ops/prepare-cutover-runtime.py
python3 ops/prepare-cutover-runtime.py --execute
```

El plan se guarda privadamente, limita por IDs y conserva el hash de los
payloads. Aísla los correos antiguos en `legacy-cutover-20261101`, sin borrar
trabajos ni enviarlos. Inactiva dispositivos heredados, caduca tokens antiguos
y cierra entregas/dispatches históricos. Inicializa los avisos de inventario sin
enviarlos y activa el escaneo de vencimientos con el ID máximo del snapshot final
como límite histórico. Una segunda ejecución es inocua y no
incluye registros nuevos. No consumir la cola heredada automáticamente.

Guardar paquete de código revisado `release-source.tar.gz` y su manifiesto en
el directorio privado. Comprobar que el helper de backup instalado en
`/usr/local/lib/samy-backup/` coincide con el revisado. No guardar allí el keystore
Android ni su contraseña.

Arrancar app/gateway privados con el entorno de producción y rutas públicas
deshabilitadas; esperar el healthcheck. Comprobar consultas de solo lectura por
loopback `18182` con Host de los dominios definitivos. No usar los scripts
funcionales que cobran datos QA contra la base comercial.

```sh
python3 ops/release-backup.py --verify-restore
```

Exigir éxito del backup y de la recuperación antes de aceptar cobros. No se
podrá inicializar otra vez el repositorio: ya existe y está probado.

## 4. DNS, certificado y apertura

Estado revisado antes del corte: no existen registros `@`, `www` ni `sistema`.

La política de lectura pública está en `../ops/r2-public-cors.json`. Si se cambia
en el futuro con objetos ya cacheados, purgar únicamente la caché del host de
imágenes según la [documentación de Cloudflare](https://developers.cloudflare.com/r2/buckets/cors/).
Hay cuatro registros que se conservan: R2 `imagenes`, CNAME `rsend`/`send` y TXT
DKIM `resend._domainkey`. SSL/TLS está en **Completo** y debe pasar a **Completo
(estricto)** al terminar. No usar Flexible ni desactivar validación TLS.

Registros preparados en `../ops/cloudflare-web-cutover.zone`. Crear inicialmente
**Solo DNS**, TTL 300, para que el HTTP challenge del Traefik existente llegue
al VPS. No crear AAAA sin IPv6 comprobada. Con snapshot final aprobado:

1. Establecer `SAMY_PUBLISH_ROUTING=true` en el entorno privado final.
2. Aplicar únicamente `compose.release.yaml` + `compose.publish.yaml` del
   proyecto `samy-production`; no reiniciar ni cambiar Traefik compartido.
   Gateway es el único servicio unido a la red externa `proxy`.
3. Publicar los tres registros web, esperar resolución y emisión de certificados
   del resolver existente `letsencrypt` por HTTP01. Verificar cadena, SNI y
   nombres de los tres hosts **sin** `-k` ni excepciones del navegador.
4. Activar proxy Cloudflare en los registros web y **Completo (estricto)**.
   Repetir comprobaciones HTTPS. Conservar los registros de correo e imágenes.
5. Arrancar `worker`, `push_worker` y `scheduler` del perfil `live`; comprobar
   las colas `default`, `catalog-imports`, `sale-notifications` y
   `push-notifications`, además de `notifications:recover-push` cada minuto.
   La cola heredada queda excluida.
6. Activar `samy-backup-production.timer` y `samy-backup-recover.service`; verificar próxima ejecución y estado
   del respaldo. La limpieza automática de fotos continúa apagada.

## 5. Comprobación tras el corte

- Catálogo en raíz, filtros/fichas/imágenes, www canónico, robots y sitemap.
- Login y permisos del sistema; ningún módulo administrativo en el host público.
- APK definitiva abre la API HTTPS, inicia sesión y registra su nuevo dispositivo.
- Registrar permiso web del nuevo origen y permiso Android si lo solicita.
  Enviar un único aviso sintético al gerente autorizado, comprobar recepción y
  navegación al tocarlo en navegador y tablet. No enviar avisos a clientes.
- Comparar totales y stock reales de forma de solo lectura con el manifiesto.
  Pruebas que crean ventas/abonos se mantienen en el entorno sintético.
- Confirmar que los servicios ajenos al proyecto siguen activos y que el origen
  antiguo permanece en mantenimiento. Entregar nuevo acceso y APK al operador.

## Recuperación ante fallo

**Antes de aceptar escrituras en el destino:** retirar solo las rutas/registros
web nuevos, detener los servicios comerciales del proyecto Samy nuevo y volver
a habilitar el origen con sus archivos/base originales y cron conservados. No
eliminar volúmenes ni archivos de respaldo. Correo e imágenes no requieren cambios.

**Después de una escritura comercial en destino:** poner ambos accesos en
mantenimiento y detener solo workers/scheduler de Samy. Capturar base, archivos
y medios del destino antes de actuar. No basta con cambiar DNS al origen, porque
se perderían los cobros nuevos. Conciliar/restaurar el snapshot actual en un
destino aislado, verificar movimientos y elegir una sola base como autoridad.
No permitir cobros simultáneos en dos bases.

Para recuperar fuera del VPS, se necesitan el repositorio Restic del bucket
privado, la contraseña custodiada fuera de la app, las credenciales R2 y el
paquete de código de la captura. Los secretos se obtienen de los resguardos
privados del usuario, nunca de este documento. Primero `restic check`, luego
`restore`, hashes y restauración SQL en un esquema nuevo. No sobrescribir la
única copia disponible ni `samy_restore`.
