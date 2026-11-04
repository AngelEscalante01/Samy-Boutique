# Aceptación privada en VPS — 1 de noviembre de 2026

Estado: **prueba funcional privada completada**, sin publicación ni cambios a
producción. La web y Android usan el código local actual, incluidos los cambios
sin commit. La evidencia está en
`../../samy-boutique-app/docs/evidence/vps-qa-2026-11-01/verification.json`.

## Aislamiento y acceso

- Directorio nuevo: `/home/angel/samy-vps-qa-20261101`; Compose `samy-vps-qa`.
- Seis servicios propios: web, gateway, MariaDB 10.6.28, worker, scheduler y
  Mailpit. Redes y volúmenes propios; ninguna red externa de otros proyectos.
- Gateway únicamente en `127.0.0.1:18180`. Todos los servicios llevan
  `traefik.enable=false`. No se cambió Traefik, firewall, DNS ni ningún sitio
  productivo. Los **28 contenedores que estaban activos antes siguen activos**.
- Base nueva `samy_vps_qa`, host `mysql`, usuarios y catálogo sintéticos.
  `samy_restore` nunca se conectó. El entrypoint rechaza ese nombre y también
  cualquier bucket distinto de los dos buckets R2 de pruebas; pruebas negativas
  con salida 1, antes de arrancar Laravel.
- R2 `samy-public-test` / `samy-private-test`, Firebase `samy-boutique-dev`.
  Solo se usaron objetos sintéticos nuevos de esta prueba. Correo dirigido a
  Mailpit; envío push general deshabilitado. Dos envíos FCM manuales acotados,
  uno al Android registrado y otro al navegador registrado del gerente QA.
- Secretos fuera del código, imagen, paquete y evidencia: archivos privados del
  directorio de pruebas. Cuenta de servicio de desarrollo montada solo lectura.
- Las imágenes MinIO previamente usadas en local no se pudieron descargar del
  registro. El entorno final usa R2 de pruebas directamente; no se cambió el
  Compose local para sortear esa descarga.

La dirección web es `http://127.0.0.1:18180`; Android usa
`http://127.0.0.1:18180/api`. El acceso remoto va cifrado dentro del túnel SSH; la
URL HTTP es el extremo local del túnel, no un puerto público del VPS. La tablet
usa `adb -P 5038 reverse tcp:18180 tcp:18180`. Requiere mantener el túnel y USB.
El dominio comprado **samyboutique.com.mx** permanece sin cambios de esta tarea.

Para reabrir el túnel, usar el host VPS confirmado en Hostinger, usuario `angel`,
validación de clave del host y keepalive; nunca guardar la contraseña en el
comando:

```sh
ssh -N -o ServerAliveInterval=15 -o ServerAliveCountMax=3 \
  -o ExitOnForwardFailure=yes -o StrictHostKeyChecking=yes \
  -L 127.0.0.1:18180:127.0.0.1:18180 angel@HOST_VPS_CONFIRMADO
```

## Resultados

| Comprobación | Resultado |
| --- | --- |
| HTTP real desde el VPS | **78 aprobadas, 0 fallidas**: login/logout, permisos gerente/cajero, pantallas nativas, clientes, SKU de variante, venta mixta, apartado, abono y conflictos/repeticiones idempotentes |
| Web móvil | 8 módulos a 390 px, sin desborde del documento: dashboard, POS, productos, clientes, movimientos, apartados, reportes y configuración |
| Fotos | Dos imágenes de color sintéticas cargadas desde Fotos en tabla, asignadas Rojo/M y Azul/L; visibles en editor, variantes y Android mediante R2 |
| Archivos privados | Lectura autenticada y URL temporal: correctas/200; misma URL sin firma rechazada con 400. Objeto público de prueba: 200 |
| Comprobantes web | Venta, apartado y abono, cada uno a 58 y 80 mm: seis vistas sin desborde, sin abrir impresión física |
| Exportaciones | PDF 29,008 bytes, cabecera PDF válida; Excel 5,499 bytes, ZIP XLSX válido; ambos HTTP 200 |
| Venta desde tablet | #2, total $199, efectivo recibido $250, cambio $51; una venta/pago y una reducción de stock |
| Abonos cruzados | Web registró $25 y Android mostró saldo $75; Android registró otros $25 y web mostró saldo $50 |
| Offline real Android | Se retiró solo el acceso de la app al VPS: cobro deshabilitado y carrito conservado. Reinicio en frío sin acceso mantuvo sesión, catálogo y carrito de una prenda |
| Respuesta perdida | Se descartó la respuesta 201 de la venta #3 después del commit. Se reinició Android y se recuperó: mismo ID, mismo hash de clave idempotente, sin otros pagos ni movimientos de stock |
| Firebase Android | Aceptado por FCM, aviso real recibido, toque automatizado abrió Centro de avisos con el aviso correcto |
| Firebase web | Usuario aceptó permiso del nuevo origen; FCM aceptó el envío y Chrome registró recepción real en primer plano. Bandeja actualizada con el aviso correcto |
| Flutter final | **136 pruebas aprobadas**, incluidas tres regresiones nuevas de latencia de checkout y una de fechas españolas |
| Analizador / compilación | 0 errores, 0 advertencias, 260 avisos informativos existentes; APK PERF release compilada y firma de desarrollo verificada |

La prueba de respuesta perdida usa solo un proxy local de bucle interno entre
ADB y el túnel. Registro sin tokens ni payloads: `fault-proxy-sales.json`.
Se cerró ese proxy y se restauró el acceso directo antes de terminar.

## Correcciones encontradas durante esta aceptación

1. Checkout reinicializaba todos los campos al llegar la vista previa del servidor.
   Ahora se inicializa al abrir; una respuesta tardía o actualización del total
   conserva efectivo editado, método, referencia y pagos mixtos. Solo el importe
   sugerido sin editar se ajusta al total confirmado. Se evita solicitar dos
   verificaciones simultáneas. Prueba física: $250 permanecieron tras volver a
   verificar $199, y el comprobante conservó cambio $51.
2. Android no inicializaba los datos de fechas españolas; la confirmación podía
   caer al texto ISO crudo. Se inicializan antes de abrir la app. Comprobación
   física final: `1 de noviembre 2026, 13:37` para la venta recuperada #3.
3. Se alineó el entorno nuevo con `APP_TIMEZONE` y `TICKET_TIMEZONE` en
   `America/Mexico_City`. La primera fixture, creada en UTC durante el arranque,
   se convirtió una sola vez conservando el instante de la venta. El helper de
   alineación se restringe a esta base sintética y rechaza repetirse; **no es un
   procedimiento de migración de producción**.

## Conciliación y APK

Estado final: **3 ventas por $597**, 4 pagos de ventas, 1 apartado de $199 con
4 abonos por $149 y saldo $50; 7 comprobantes idempotentes; stock por variante
`[18, 19, 19, 20]`; cero jobs fallidos. Web y Android muestran esos mismos totales.
Carrito de la tablet vacío al finalizar, como al iniciar la prueba; el catálogo y
carrito del servidor local anterior conservan su ámbito separado.

APK: `samy-boutique-app/artifacts/Samy-Boutique-VPS-Pruebas-2026-11-01.apk`.
SHA-256:
`2083f64108885add0f991e33e00a1b412d7ccc8cdea449077e06de5435c608e5`.
Coincide con `base.apk` extraída de la instalación en Galaxy Tab A9+. Se actualizó
con `install -r`, sin borrar datos. Sigue siendo la variante nativa Flutter
`com.example.samy_boutique_app.perf`, sin WebView; no es la identidad/firma final.

Los JSON, capturas, logs y hashes de fuente se guardan en la carpeta de evidencia.
La suite HTTP se ejecuta sobre la fixture inicial: sus claves hacen seguros los
reintentos, pero las aserciones de saldos iniciales no sustituyen la conciliación
posterior a las operaciones manuales de web/tablet. La suite PHP histórica local
no se declara reejecutada completa en el VPS; aquí se comprobó el servidor real
mediante HTTP, UI y base sintética conciliada.

## Antes de publicar

Siguen separados: impresora física de tickets del cliente; identidad, firma y
distribución definitivas de Android; HTTPS/orígenes públicos de Cloudflare,
Firebase y R2; captura consistente de los datos comerciales y ensayo del corte,
conciliación y regreso. No se realizó ninguna de esas acciones productivas.
Este Compose usa `artisan serve` para aceptación privada y **no es la receta de
publicación definitiva**. El borrado automático de fotos tras 15 días sigue
pospuesto.