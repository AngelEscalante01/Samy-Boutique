# Laboratorio Docker local

Ejecutar desde `Samy-Boutique` en WSL/Linux. Requiere Docker Engine y Compose v2 o posterior. Este laboratorio usa el proyecto **samy-sandbox**; no reutiliza otros contenedores, redes, volúmenes, bases de datos ni archivos `.env`. El servicio llamado `mysql` ejecuta **MariaDB 10.6.28**, conforme al motor observado en el respaldo de origen; Laravel conserva el driver `mysql` compatible.

```sh
dc() { docker compose --env-file docker/local/compose.env -f compose.local.yaml "$@"; }
dc config --quiet
dc build web
dc up -d mysql mailpit minio buckets
# Inicializa SOLO samy_local, sin seeders, sin importar datos ni borrar tablas.
dc run --rm --no-deps web php artisan migrate --force
dc up -d web gateway queue-default queue-sales scheduler
dc ps
```

El entrypoint rechaza ambientes distintos de `local`, host DB distinto de `mysql` o nombre de base distinto de `samy_local`. Nunca ejecutar `migrate:fresh`, `db:wipe` ni importar un respaldo real en este laboratorio. No se crea un usuario administrador automáticamente. Crear únicamente fixtures sintéticos mediante los comandos de prueba del proyecto, después de revisar sus efectos.

| Servicio | Dirección |
| --- | --- |
| Aplicación | http://localhost:18080 |
| Mailpit (correo capturado) | http://localhost:18025 |
| MinIO S3 | http://localhost:19000 |
| MinIO consola | http://localhost:19001 |

MySQL y SMTP no publican puertos. Los demás puertos se ligan exclusivamente a `127.0.0.1`. MinIO usa `samy-local` / `samy-local-minio-password`, valores públicos de prueba. La clave Laravel también es local y pública. **No usar este Compose para producción.**

## Aislamiento

- La red `sandbox` es interna y tiene la subred explícita `10.254.254.0/28` para evitar el agotamiento de los pools automáticos de este host. App y MariaDB no tienen salida externa. `gateway`, Mailpit y MinIO conectan también con `edge` para publicar solo sus puertos locales. `gateway` no recibe secretos ni archivos de aplicación. No montar el socket Docker.
- No hay bind mounts del repositorio, `.env`, `storage`, respaldos ni bases existentes. El contexto de build usa una lista permitida en `docker/local/Dockerfile.dockerignore`; excluye cache, uploads, vendor local y credenciales. Los assets se reconstruyen sin variables Firebase reales.
- Usar siempre `--env-file docker/local/compose.env` evita que Compose lea el `.env` del proyecto. Las variables de servicios son valores locales literales, sin interpolación de secretos del host.
- SMTP apunta a Mailpit; push y escaneo automático de vencimientos están desactivados. Los workers consumen `database/default` y `sale_notifications/sale-notifications` por separado. El scheduler puede ejecutar tareas sobre los datos sintéticos locales.
- El build y la descarga de imágenes sí requieren Internet desde Docker para obtener dependencias. La red interna aplica a los servicios en ejecución; no es un firewall del navegador/host. No importar tokens ni datos personales.
- Los cuatro volúmenes pertenecen a este proyecto. `dc down` conserva sus datos; no usar `down -v` salvo que se desee borrar expresamente todo el laboratorio.

El worker `queue-default` también consume `catalog-imports`; conserva timeout de 60 segundos. Ajustar timeout/retry_after conjuntamente si un import validado requiere más tiempo, sin permitir dos ejecuciones simultáneas del mismo job.

## Almacenamiento de objetos

El laboratorio web usa discos S3 compatibles de MinIO para productos/carrusel (`samy-public-local`) y clientes (`samy-private-local`). `buckets` prepara ambos y solo el público permite lectura anónima. El logo configurado en Compose se cargó con `media:migrate-brand-logo --execute`, se releyó con SHA-256/tamaño idénticos y se activó con `BRAND_LOGO_DISK`/`BRAND_LOGO_PATH`. La migración de imágenes de producto permanece deshabilitada (`MEDIA_MIGRATION_ENABLED=false`). R2 real se ensaya por separado con buckets de prueba y credenciales privadas fuera del repositorio.

Se fijaron imágenes de MinIO en Quay y de Mailpit en ECR, comprobadas en este host. `buckets` es un trabajo de inicialización que sale con código 0; la aplicación espera su éxito al arrancar.

Las URL públicas locales usan `localhost:19000/samy-public-local/...`; las fotos privadas se sirven desde una ruta autenticada. No cambiar el endpoint a R2 real para resolver problemas locales de red.

## Verificación y operación

```sh
dc logs --tail=100 web queue-default queue-sales scheduler
dc exec web php artisan migrate:status
dc exec web php artisan schedule:list
dc exec web php artisan queue:failed
dc exec web php -r '$s=@fsockopen("1.1.1.1",443,$e,$m,3); exit($s ? 1 : 0);'
dc exec minio curl -f http://localhost:9000/minio/health/live
dc down
```

La prueba de salida devuelve cero si no se puede conectar; repetirla tras cualquier cambio de red. Verificar también que los correos aparezcan solo en Mailpit y que una descarga anónima del bucket privado devuelva 403. `/up` prueba el arranque HTTP de Laravel, no el funcionamiento completo de ventas ni el esquema DB.

Cambios de código requieren `dc build web` y `dc up -d --force-recreate web queue-default queue-sales scheduler`; no hay sincronización automática con el árbol de trabajo. El gateway resuelve el nombre del contenedor web nuevamente después de recrearlo; `/up` debe seguir respondiendo 200. Las etiquetas de imágenes están fijadas por versión, no por digest; revisar actualizaciones y registrar digests antes de un despliegue real.

## Ensayo privado de restauración

Solo cuando se haya autorizado expresamente un respaldo concreto: detener web, workers y scheduler; iniciar únicamente MariaDB; ejecutar desde PowerShell 7 `docker/local/restore-private.ps1 -Source <ruta-privada.sql.gz> -ExpectedSha256 <hash-verificado> -ReportDirectory <directorio-privado-fuera-del-repo>`. No hacerlo con respaldos arbitrarios: el script valida destino/hash y sentencias de cambio de base, pero no es un analizador de seguridad SQL.

El script rechaza sobrescribir `samy_restore`, restaura con un usuario que solo tiene permisos sobre esa base y registra conteos y sumas numéricas antes de cualquier migración. Esos informes contienen agregados privados y no deben versionarse. No arranca Laravel ni aplica migraciones. Comparar los mismos agregados después del ensayo de migraciones, excluyendo únicamente metadatos de la tabla `migrations`; no modificar filas reales para hacer pasar la comparación. Mantener detenidos los consumidores de jobs y nunca mostrar esta base en la UI.

Después de migrar la base aislada con un contenedor de una sola ejecución y credenciales locales limitadas a `samy_restore`, ejecutar `docker/local/compare-restore.ps1 -ReportDirectory <directorio-privado>` desde PowerShell. Escribe informes privados posteriores y un resumen de diferencias. Nunca usar el servicio web o los workers para operar sobre `samy_restore`.

## Evidencia de preparación (24 de octubre de 2026)

- `docker compose ... config --quiet`: aprobado con Docker 29.8.0 y Compose 5.5.1.
- Puertos elegidos sin colisión con contenedores existentes en el momento de revisión.
- Primer build WSL: timeout HTTPS a `auth.docker.io`. Reintento con `docker.exe` Windows resolvió las tres imágenes e inició npm/apt. MariaDB 10.6.28 descargada correctamente, digest `sha256:88ac718dafee1e8fb415233c04f314f3c3866af99177ce25d436106c62090461`. No confundir descarga o validación YAML con un arranque probado.
- Posteriormente WSL dejó de responder a nuevos comandos (`Wsl/Service/0x8007274c`). No se reinició WSL ni Docker para no interrumpir los demás proyectos activos.
- El reintento Windows tampoco completó: npm/apt permanecieron sin salida y `up -d mysql` quedó creando la red interna durante más de seis minutos; consultas del daemon seguían respondiendo. Se cancelaron únicamente los clientes Compose de este laboratorio y sus hijos. Se creó el volumen vacío `samy-sandbox_mysql-data`; no apareció ningún contenedor Samy ni se importó el respaldo. Build, arranque, HTTP, bloqueo de salida, mail y S3 quedan **sin validar**.
- Existe autorización separada para ensayar el respaldo privado en una base nueva `samy_restore`, pero no se ha ejecutado. Debe hacerse con workers/scheduler detenidos, sin conectar web a esa base, sin importar el `.env` productivo y sin arrancar jobs del respaldo. El entrypoint normal solo admite `samy_local`: un ensayo de restauración necesita un procedimiento separado y revisado. El respaldo mezcla InnoDB/MyISAM; no se ha demostrado consistencia transaccional entre motores.

Referencias: [redes internas de Compose](https://docs.docker.com/reference/compose-file/networks/), [servicios y puertos](https://docs.docker.com/reference/compose-file/services/), [colas Laravel 11](https://laravel.com/docs/11.x/queues), [S3 y MinIO Laravel 11](https://laravel.com/docs/11.x/filesystem).

## Reanudación del laboratorio web

Docker volvió a estar disponible. Se corrigió el build para que la etapa Vite tenga Ziggy desde Composer, se fijó `DB_COLLATION=utf8mb4_unicode_ci` para MariaDB 10.6, se añadieron subredes exclusivas y un `gateway` sin credenciales para exponer el HTTP local. La imagen se construyó, **todas las migraciones corrieron sobre `samy_local` vacío**, MariaDB quedó healthy, `/up` respondió 200, `/catalogo` 200 y `/products` redirigió al login. No se restauró el respaldo comercial ni se aplicó ninguna migración a Neubox. El laboratorio estaba levantado al terminar esta prueba en `http://127.0.0.1:18080`.

MinIO, Mailpit, workers y scheduler quedaron activos; el trabajo `buckets` terminó con código 0. Esto no sustituye la prueba R2 real, documentada en `MIGRATION_READINESS_2026-10-24.md`. Antes de una UAT funcional hacen falta usuarios y productos sintéticos y comprobar el flujo completo desde navegador.

El respaldo privado identificado por SHA-256 se restauró después en `samy_restore`, separado de `samy_local`; 42 tablas. Todas las migraciones nuevas terminaron luego de conciliar diferencias de esquema de tokens, caja recibida y pagos de apartados. La segunda ejecución de `migrate` no tiene pendientes. `compare-restore.ps1` contrastó 42 tablas originales y 151 columnas numéricas: cero cambios de conteos o sumas fuera de la tabla técnica `migrations`. Los informes permanecen fuera del repositorio. Web, colas y scheduler siguen conectados solo a `samy_local` sintético.

La imagen local se actualizó a Laravel 12.70.1 y PHPUnit 11.5.57, manteniendo PHP 8.2.28. Suite completa: SQLite 113 aprobadas, 3 omitidas, 846 aserciones tras el cambio de logo; MariaDB 115 casos, 3 omitidas, 839 aserciones antes de ese cambio sin efectos de base. `composer validate`, `composer audit` y `composer check-platform-reqs --no-dev` terminaron sin problemas. `/up`, `/catalogo` y `/login` respondieron 200 después de recrear web y workers; `queue:failed` quedó vacío. La instalación de Neubox no se modificó.
