# Guía de Deploy — Samy Boutique
**URL destino:** `https://samy-boutique.makedweb.com/`

---

## Archivos preparados localmente

| Archivo | Propósito |
|---|---|
| `.env.production` | Variables Vite para el build (ya ejecutado) |
| `deployment/env.production.template` | Plantilla del `.env` que va **en el servidor** |
| `deployment/htaccess.production` | `.htaccess` con `RewriteBase /` para producción |
| `public/build/` | Assets compilados (listo para subir) |

---

## PASO 1 — Preparar credenciales del servidor

Abre `deployment/env.production.template` y rellena los datos reales:

```
DB_HOST=      ← IP o hostname de MySQL en el hosting
DB_DATABASE=  ← Nombre de la base de datos creada
DB_USERNAME=  ← Usuario MySQL
DB_PASSWORD=  ← Contraseña MySQL
```

Luego guarda ese archivo como `.env` (sin extensión) para subir al servidor.

---

## PASO 2 — Qué subir al servidor

Sube **todo el proyecto** excepto:

```
❌ NO subir:
  node_modules/
  .env              (el local, con datos de desarrollo)
  .env.production   (solo era para el build local)
  storage/app/*     (se genera en servidor)
  storage/framework/cache/*
  storage/framework/sessions/*
  storage/framework/views/*
  bootstrap/cache/*
```

Cosas que SÍ debes subir o confirmar:
```
✅ SÍ subir:
  app/
  bootstrap/app.php
  config/
  database/
  public/           ← incluyendo public/build/ recién generado
  resources/
  routes/
  storage/          ← estructura de carpetas (puede estar vacía)
  vendor/           ← O ejecutar composer install en el servidor
  composer.json / composer.lock
  artisan
  .env              ← el que preparaste en Paso 1
```

---

## PASO 3 — Reemplazar .htaccess en el servidor

En el servidor, reemplaza `public/.htaccess` con el contenido de
`deployment/htaccess.production` (que tiene `RewriteBase /`).

El archivo local `public/.htaccess` tiene `RewriteBase /boutique/Samy-Boutique/public/`
(para XAMPP local) — en producción debe ser `RewriteBase /`.

---

## PASO 4 — Configurar Document Root

En tu panel de hosting (cPanel, Plesk, etc.):
- El **Document Root** del dominio `samy-boutique.makedweb.com` debe apuntar
  a la carpeta `public/` de tu proyecto.

---

## PASO 5 — Comandos en el servidor (SSH o terminal del hosting)

Ejecuta estos comandos **desde la raíz del proyecto** en el servidor:

```bash
# 1. Instalar dependencias PHP (si no subiste vendor/)
composer install --no-dev --optimize-autoloader

# 2. Permisos de escritura
chmod -R 775 storage bootstrap/cache

# 3. Primera vez: ejecutar migraciones
php artisan migrate --force

# 4. Primera vez: sembrar datos iniciales
#    (crea usuarios gerente@gmail.com y cajero@gmail.com con password: 'password')
php artisan db:seed --force

# 5. Crear symlink de storage para imágenes
php artisan storage:link

# 6. Cachear configuración para mejor rendimiento
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

## PASO 6 — Cambiar contraseñas por defecto

Después del primer deploy, **cambia las contraseñas** de los usuarios creados
por el seeder desde el panel de administración:

- `gerente@gmail.com` / `password` → cambiar
- `cajero@gmail.com` / `password` → cambiar

---

## PASO 7 — Actualizar la app Flutter

La app Flutter necesita apuntar a la nueva URL de producción.

Para compilar con la API de producción:

```powershell
# Android (dispositivo físico)
flutter run -d <DEVICE_ID> --dart-define=API_BASE_URL=https://samy-boutique.makedweb.com/api

# Build APK de producción
flutter build apk --release --dart-define=API_BASE_URL=https://samy-boutique.makedweb.com/api
```

---

## PASO 8 — Verificar funcionamiento

1. Abre `https://samy-boutique.makedweb.com/` → debe cargar la pantalla de login
2. Login con `gerente@gmail.com` / `password`
3. En Flutter: prueba login con la nueva URL
4. Sube una imagen de producto y verifica que se muestra correctamente
5. Si hay errores 500: revisa `storage/logs/laravel.log` en el servidor

---

## Troubleshooting frecuente

| Problema | Solución |
|---|---|
| Error 500 en todo | Revisar `storage/logs/laravel.log`, verificar permisos de `storage/` |
| Error 403 / .htaccess no funciona | Verificar que `mod_rewrite` esté habilitado en el servidor |
| Imágenes de productos no cargan | Ejecutar `php artisan storage:link` |
| "No application encryption key" | Verificar que `APP_KEY` esté en `.env` del servidor |
| Flutter: Error CORS | Verificar que el `.htaccess` de producción tenga los headers CORS |
| Sesiones no persisten (web) | Verificar `SESSION_DOMAIN=samy-boutique.makedweb.com` en `.env` |
| Login infinito en web | Ejecutar `php artisan session:table` + migrate si `SESSION_DRIVER=database` |

---

## Para futuros deploys (actualizaciones)

```bash
# En el servidor, después de subir cambios:
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --force   # solo si hay nuevas migraciones
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```
