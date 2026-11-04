# Dominio de Samy Boutique

Dominio comprado por el usuario el 29 de octubre de 2026: **samyboutique.com.mx**. Cloudflare confirma registro activo y administración DNS completa.

## Destinos acordados

- URL canónica futura: `https://samyboutique.com.mx`.
- La raíz abre el catálogo público; sistema autenticado y API Android se separan en `https://sistema.samyboutique.com.mx` (`/api` para Android).
- Imágenes públicas: `https://imagenes.samyboutique.com.mx`, conectado únicamente a `samy-public-prod` con TLS mínimo 1.2. El bucket privado no tiene acceso público.
- `www.samyboutique.com.mx`: redirigir a la URL canónica cuando se publique la web.
- Correo transaccional: `notificaciones@samyboutique.com.mx`, nombre `Samy Boutique`.
- Proveedor de salida: Resend, cuenta `digitalex.mx` seleccionada por el usuario en Chrome.
- El desarrollo local continúa en `http://127.0.0.1:18080`. El 1 de noviembre se autorizó y completó la aceptación privada del VPS mediante túnel SSH, sin cambiar DNS ni producción; ver `VPS_PRIVATE_ACCEPTANCE_2026-11-01.md`. La publicación del dominio sigue pospuesta.

## Preparación del correo

Resend registró el dominio con ID `d7c2b2a2-2cf6-4782-9d44-8a123e38b8e5`, región `us-east-1`. Los registros exigidos por su interfaz el 29 de octubre son:

| Tipo | Nombre | Contenido | Proxy | TTL |
| --- | --- | --- | --- | --- |
| TXT | `resend._domainkey` | Clave pública DKIM específica mostrada por Resend | No aplica | Auto |
| CNAME | `rsend` | `rsend.forge.rmta.net` | Solo DNS | Auto |
| CNAME | `send` | `send.forge.rmta.net` | Solo DNS | Auto |

La recepción de correo en Resend permanece deshabilitada. Un remitente transaccional no crea una bandeja de entrada; la recepción o reenvío se configura aparte si se necesita. No sustituir estos registros por valores históricos de otra cuenta o proveedor.

El usuario confirmó expresamente publicar los tres registros y crear la clave limitada a este dominio. Los tres registros se guardaron en Cloudflare y se comprobaron en el DNS público mediante el resolver `1.1.1.1`. Resend confirmó **Verified**, con los eventos `DNS verified` y `Domain verified`.

Se creó la clave `Samy Boutique · samyboutique.com.mx · desarrollo`, permiso **Sending access**, restringida exclusivamente a `samyboutique.com.mx`. La configuración vigente está fuera del repositorio en `C:\Users\Angel\.codex\private-configs\samy-resend-development.env`; sustituyó el remitente temporal de Digitalex. Web, colas y scheduler locales se recrearon con el nuevo remitente y la limitación de todos los destinatarios al Gmail de prueba.

Prueba real autorizada desde `notificaciones@samyboutique.com.mx`: referencia `QA-WEB-20261029-220026`, aceptada por SMTP y encontrada en **INBOX** de `angelescalante0501@gmail.com`. ID Gmail: `1a189adc7c4c50ca`. La clave no se imprimió ni se incorporó al código o a la imagen Docker.

## Preparación de la web para el VPS

Cuando se autorice la publicación, confirmar primero la IPv4/IPv6 del VPS elegido y el certificado del servidor. Solo entonces crear los registros web y configurar el host. No apuntar a localhost, a direcciones deducidas ni al alojamiento anterior.

Valores que deberán establecerse en el entorno privado del VPS, después de autorizar esa fase:

```dotenv
APP_URL=https://sistema.samyboutique.com.mx
PUBLIC_URL=https://samyboutique.com.mx
SYSTEM_URL=https://sistema.samyboutique.com.mx
SESSION_SECURE_COOKIE=true
MAIL_FROM_ADDRESS=notificaciones@samyboutique.com.mx
MAIL_FROM_NAME="Samy Boutique"
```

Conservar cookie de sesión limitada al host salvo que se diseñe una necesidad explícita entre subdominios. Las credenciales del VPS, Resend y Firebase no se incluyen en este documento. `MAIL_DEVELOPMENT_TO` es una limitación exclusiva del entorno local y no configura destinatarios de producción.

La preparación final y la recuperación se documentan en `PUBLICATION_PREPARATION_2026-11-01.md`; el corte concreto y su reversión en `RELEASE_CUTOVER_2026-11-01.md`. Los registros web `@`, `www` y `sistema` siguen sin publicarse al cerrar la preparación. Los registros de correo se conservan.
