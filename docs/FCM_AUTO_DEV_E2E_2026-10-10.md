# Prueba única FCM automática DEV — 10 oct 2026

**Resultado: `AUTOMATIC_DEV_FCM_E2E_PASS`**, para esta única muestra. El temporizador envió una sola solicitud FCM a la instalación Redmi y Android mostró el aviso. La muestra no establece un SLA ni una latencia en tiempo real.

## Alcance y precondiciones

- Proyecto Laravel y Firebase: `samy-boutique-dev`; `NOTIFICATIONS_PUSH_ENABLED=false` antes, durante y después del ensayo.
- Timer `samy-push-dev.timer` activo, calendario de un minuto. No se invocó manualmente el procesador.
- Despachos anteriores 17, 18, 22 y 24: `pending`, sin entregas ni intentos antes del ensayo. Se preservaron.
- Redmi Note 8 Pro conectado por ADB, paquete `com.example.samy_boutique_app`, permiso Android `POST_NOTIFICATION: allow` e instalación Laravel #7 (`d03ad2c2-f516-4dd0-8a6e-d1ab2c6ca63b`), activa y concedida. Su hash de token aparecía en una sola instalación. La app mostró el Centro de avisos con sesión de usuario #5 activa. La APK instalada contiene sender ID DEV `571837949468` y app ID `1:571837949468:android:a003a5d9642c224c6721d9`.
- Se creó únicamente el aviso diagnóstico #25, despacho #25 y entrega #22, dirigida al dispositivo #7. No se creó venta ni se ejecutó SMTP.

## Selección segura y ejecución

Se instaló temporalmente un comando DEV de un solo intento y se apuntó el servicio del timer al **ID exacto de entrega 22**. El comando exigía `APP_ENV=local`, proyecto DEV, compuerta global cerrada, ID/UUID del Redmi, token único y coincidente, aviso diagnóstico exacto, despacho pendiente y contador de intentos cero. Reclamó la entrega y fijó `attempt_count=1` antes de llamar a FCM. Cualquier ciclo posterior quedaba sin posibilidad de reenvío. El código y comando temporales se retiraron inmediatamente después, y el servicio volvió a `notifications:process-push`.

| Momento | Evidencia, hora America/Mexico_City |
| --- | --- |
| T0, aviso creado | 11:06:54, `system_notifications` #25 |
| T1, timer empezó | 11:08:00.414, marca del journal `DEV_ONE_SHOT_TIMER_START` |
| T2, llamada FCM iniciada | 11:08:00, marca de aplicación con precisión de un segundo |
| T3, respuesta FCM | 11:08:00, marca de aplicación con precisión de un segundo; resultado de aceptación registrado en journal a las 11:08:00.754 |
| T4, aviso publicado en Android | `NotificationRecord` del paquete Samy, ID #25, `when=1791652080455` (11:08:00.455 según el reloj Android). Esta marca es de publicación del aviso, no una sonda del transporte FCM. |
| T5, visualización comprobada | Captura del panel de notificaciones a las 11:09:41.980. Muestra logo `SB`, título y mensaje. La captura ocurrió después de que el aviso ya estaba presente; no representa la latencia de presentación. |

Espera T0→T1: **66.414 s** con precisión limitada por el segundo de T0. T1→resultado registrado: **0.340 s**, incluyendo reclamo de base, HTTP FCM y actualización de base; la duración HTTP aislada no quedó instrumentada. La estimación T0→publicación Android es **aprox. 66.9 s**. Una lectura ADB posterior mostró que el reloj epoch del Redmi estaba unos 0.42 s detrás del host, con incertidumbre aproximada de ±0.15 s por la ida y vuelta ADB. La hora de pared en la UI del teléfono estaba una hora adelantada respecto a la zona del servidor; se compararon epoch y zona `America/Mexico_City`, no solo las horas visibles. T0→captura de confirmación visual: **167.98 s**, límite superior del momento de observación, no del momento en que apareció.

FCM respondió `accepted=true`, sin error, con ID `projects/samy-boutique-dev/messages/0:1791652080706723%d1ce9cbad1ce9cba`. Laravel registró una sola entrega `sent`, un solo intento y sin próximo reintento. El panel Android mostró **una** notificación Samy con título “Samy Boutique — Prueba automática”, cuerpo “Esta notificación fue enviada automáticamente desde Firebase DEV.” y logo `SB`. [Captura de recepción](</home/angel/proyectos/Samy-boutique-global/.perf-audit/SAMY_AUTO_FCM_REDMI_2026-10-10.png>).

Al tocar la notificación se abrió Samy Boutique en el Centro de avisos. La primera recarga de la bandeja falló porque el teléfono había perdido `adb reverse tcp:8001`; se restableció ese enlace local, sin enviar otro FCM. La bandeja mostró el aviso #25. Al abrir **solo ese aviso**, el contador pasó de 4 a 3 y Laravel guardó `read_at=2026-10-10 11:11:04 -06:00`. [Captura tras la lectura](</home/angel/proyectos/Samy-boutique-global/.perf-audit/SAMY_AUTO_FCM_READ_2026-10-10.png>).

## Estado final

- Despachos 17, 18, 22 y 24 continúan `pending`, sin entregas ni intentos. La coincidencia numérica entre despacho anterior #22 y entrega diagnóstica #22 corresponde a tablas distintas.
- Despacho diagnóstico #25 `processed`; entrega diagnóstica #22 `sent`, `attempt_count=1`, `next_attempt_at=null`; aviso #25 leído.
- Timer normal activo, habilitado y ejecutando de nuevo `notifications:process-push`; journal de las 11:10, 11:11 y 11:12: `Procesados 0 intentos push`. Compuerta global `false`.
- Venta DEV #76 presente; sin ventas, SMTP, cambios en Firebase PROD o Neubox, commit, push o deploy durante esta prueba. Los HEAD siguen `65b3f73` (Laravel) y `461a7fe` (Flutter); se conservaron los cambios previos sin confirmar.
