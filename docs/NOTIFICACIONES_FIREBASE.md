# Notificaciones Firebase de Samy Boutique

## Proyectos y apps

Se crearon en el plan Spark, sin Analytics, Hosting, Authentication ni servicios de pago:

| Entorno | Firebase Project ID | Sender ID | App Android | App ID Android |
| --- | --- | --- | --- | --- |
| DEV | `samy-boutique-dev` | `571837949468` | `com.example.samy_boutique_app` | `1:571837949468:android:a003a5d9642c224c6721d9` |
| DEV PERF | `samy-boutique-dev` | `571837949468` | `com.example.samy_boutique_app.perf` | `1:571837949468:android:4fbc896a760882876721d9` |
| PROD | `samy-boutique` | `857960335792` | `com.example.samy_boutique_app` | `1:857960335792:android:a8f96bfa2f140ab4d36e75` |

Apps web registradas:

| Entorno | App ID web | Auth domain |
| --- | --- | --- |
| DEV | `1:571837949468:web:b23e031035e6ddc66721d9` | `samy-boutique-dev.firebaseapp.com` |
| PROD | `1:857960335792:web:a8c872579f0d7ecfd36e75` | `samy-boutique.firebaseapp.com` |

FCM HTTP v1 está habilitado en ambos proyectos. Ambos siguen en Spark; no se habilitaron Analytics, Authentication, Hosting, facturación ni servicios de pago.

Claves públicas Web Push VAPID creadas:

| Entorno | Clave pública |
| --- | --- |
| DEV | `BGOOzYsiAD_x9LWrulPmV7c8CQrSf2OobG0RcJsMtGsZsHPdVvrGLmZT0Rpjt8r6tzNj_ooS3pC-hjbT2JiX2-w` |
| PROD | `BOuPLCzIIdDkxf27EKbWr-MxG7lB5mebb0nK-WMDBWgX8bu0Z-Ar5kVWhEVvE9ZpbMlFxbQFpuC-tzMcqdF1PSk` |

El JSON de DEV contiene las dos apps Android de desarrollo. Gradle usa `src/debug/google-services.json` para builds debug, `src/perf/google-services.json` para el flavor diagnóstico y `src/release/google-services.json` para producción normal. Los archivos contienen IDs cliente públicos; no incluyen credenciales administrativas. Mantenerlos separados por ambiente.

## Componentes locales

Laravel guarda la bandeja y el estado de lectura en MySQL. `notification_dispatches` es el outbox durable para expandir un aviso a instalaciones; `notification_deliveries` conserva el estado de entrega por instalación y los intentos. Una respuesta exitosa HTTP de FCM solo marca aceptación por FCM; no confirma recepción o lectura.

El envío usa OAuth 2.0 y FCM HTTP v1. La cuenta `samy-boutique-fcm-dev@samy-boutique-dev.iam.gserviceaccount.com` tiene el rol personalizado `projects/samy-boutique-dev/roles/samyFcmSender`, que contiene únicamente `cloudmessaging.messages.create`. Su JSON privado está en `/home/angel/.config/samy-boutique/firebase-dev-service-account.json`, con carpeta `0700` y archivo `0600`. No se instaló ninguna credencial en Neubox ni se creó una credencial de servidor PROD.

Variables por entorno:

```dotenv
FIREBASE_PROJECT_ID=samy-boutique-dev
FIREBASE_CREDENTIALS=/home/angel/.config/samy-boutique/firebase-dev-service-account.json
VITE_FIREBASE_PROJECT_ID=samy-boutique-dev
VITE_FIREBASE_API_KEY=
VITE_FIREBASE_AUTH_DOMAIN=
VITE_FIREBASE_STORAGE_BUCKET=
VITE_FIREBASE_MESSAGING_SENDER_ID=
VITE_FIREBASE_APP_ID=
VITE_FIREBASE_VAPID_KEY=BGOOzYsiAD_x9LWrulPmV7c8CQrSf2OobG0RcJsMtGsZsHPdVvrGLmZT0Rpjt8r6tzNj_ooS3pC-hjbT2JiX2-w
FIREBASE_JS_SDK_VERSION=12.19.0
NOTIFICATIONS_LOW_STOCK_THRESHOLD=3
LAYAWAY_EXPIRY_DAYS=30
NOTIFICATIONS_DELIVERY_BATCH_SIZE=50
NOTIFICATIONS_DELIVERY_TIMEOUT=8
```

Las claves Web y Google Services son públicas. La clave privada de cuenta de servicio y cualquier archivo JSON de servicio son secretos y no deben entrar a Git, Flutter, JavaScript, APK ni respuestas HTTP. Producción requiere los valores de `samy-boutique`; desarrollo usa únicamente `samy-boutique-dev`.

## Web y PWA

La aplicación usa Firebase Web SDK. El endpoint `/firebase-messaging-sw.js` genera el fragmento público con la configuración del entorno. VitePWA importa ese fragmento desde el Workbox service worker ya existente, con fallback si el CDN de Firebase no está disponible; no se registra un segundo worker para el mismo alcance. `VITE_FIREBASE_VAPID_KEY` usa la pública DEV localmente; producción debe usar la clave VAPID PROD de la tabla anterior.

La web no pide permiso al cargar. La acción **Activar notificaciones web** en `/notifications` solicita permiso, registra la instalación ante Laravel y obtiene el token con la clave VAPID. El cierre de sesión envía el identificador de instalación al servidor para cancelar entregas pendientes y borrar la asociación, además de eliminar el token local.

## Flutter y Android

Flutter inicializa Firebase con el plugin Google Services y los JSON específicos de variante. Android 13+ declara `POST_NOTIFICATIONS`; el permiso se solicita desde **Activar notificaciones** en el centro móvil. El mensaje visible describe la acción y, cuando aplica, incluye productos, cantidades e importes. No incluye nombres ni datos personales del cliente. Foreground muestra una notificación local; background usa la presentación del sistema para evitar duplicados. La app solo acepta un ID de notificación y vuelve a consultar la bandeja autenticada Laravel antes de navegar.

La clave de acceso de FCM se renueva y vincula por instalación. El logout invalida el token y cancela entregas pendientes en Laravel. `firebase_options.dart` no se requiere para estos builds Android: el plugin Android carga la configuración nativa por variante; no se habilitó Flutter Web como cliente.

## Reglas, automatización y operación

- Las notificaciones automáticas de negocio se dirigen a gerente/administrador. El endpoint manual exige `notifications.send`, pide confirmación, limita la audiencia a 100 cuentas y guarda remitente, prioridad y audiencia.
- Categorías, preferencia de todas las ventas y push global se guardan por usuario.
- Los tokens y las instantáneas para reintento usan casts cifrados de Laravel; los hashes se usan para invalidación, nunca se imprimen.
- Reintentos: 1, 5, 20, 60 y 180 minutos. `UNREGISTERED` invalida el token. Los pushes usan el título y resumen específicos guardados por cada evento (compra, apartado, abono, cancelación, inventario y corte); se omiten datos personales del cliente.
- El scheduler ofrece `notifications:process-push`, `notifications:scan-inventory` y `notifications:scan-layaways`. La recuperación usa lotes pequeños. El Cron de hosting existente corre cada cinco minutos, así que el respaldo puede tardar hasta cinco minutos y no equivale a tiempo real. No se agregó worker permanente ni servicio facturable.
- No se toca la cola SMTP `sale_notifications` ni se inicia su worker.

## Puesta en marcha y rollback

1. Firebase DEV/PROD, FCM HTTP v1 y claves VAPID ya están configurados. La cuenta de servicio y credencial DEV están listas. Para una prueba real de recepción, registrar un dispositivo de prueba y confirmar que el token pertenece a `samy-boutique-dev`.
2. En DEV: ejecutar migraciones, sembrar permisos, compilar el frontend, arrancar el worker propio `push-notifications` si se desea entrega más rápida y usar el scheduler como recuperación. Mantener la clave DEV fuera de `public_html`.
3. Probar con un gerente de prueba, un navegador y un emulador con Google Play Services. Confirmar permisos, bandeja, logout, rutas, token inválido y que una caída FCM no afecta el POS.
4. Para PROD, crear una credencial de servicio separada con el mismo permiso mínimo y configurarla solo en el entorno productivo durante un despliegue autorizado. No se ha aplicado a Neubox.
5. Rollback local: detener el worker push, desactivar las variables Firebase y retirar el plugin/configuración nueva. Las tablas y la bandeja solo se eliminan mediante una migración reversible después de respaldo; no usar `migrate:rollback` indiscriminadamente porque puede revertir otras migraciones.

## Estado de configuración Firebase

Los proyectos, apps Android/Web, FCM HTTP v1, claves VAPID DEV/PROD y la credencial de envío DEV están configurados. No se creó una credencial de envío PROD ni se modificó Neubox. Ningún servicio facturable ni cuenta de facturación fue habilitada. La recepción real extremo a extremo aún requiere una cuenta gerente de pruebas, un token de cliente DEV y un dispositivo/navegador apto.
