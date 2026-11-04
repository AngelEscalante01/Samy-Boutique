# Prueba de tickets con el cliente

Única aceptación física de hardware pendiente. Los formatos 58/80 mm ya pasaron
la simulación; esto no certifica el comportamiento del modelo físico del cliente.
Etiquetas y lector Bluetooth ya tienen aceptación física independiente.

Usar una cuenta y operaciones **sintéticas del entorno privado QA**; no cobrar
ni alterar inventario comercial para probar. Antes de ir, registrar marca,
modelo, conexión y ancho real del rollo de la impresora del cliente.

1. Emparejar/conectar, seleccionar la impresora en Android y elegir su ancho
   real (58 u 80 mm). Conservar la configuración después de cerrar y abrir la app.
2. Imprimir una sola copia de venta sintética con nombre largo, Ñ, variantes,
   descuento, pago mixto, efectivo recibido y cambio. Revisar legibilidad,
   márgenes, corte, última línea y ausencia de texto fuera del papel.
3. Abrir venta ya existente y reimprimir una vez. Debe salir igual; comprobar que
   no creó otra venta, pago ni salida de stock. Repetir con apartado y abono ya
   existentes, revisando saldo y referencia del pago.
4. Apagar solo la impresora e intentar una impresión. La venta debe conservar
   su resultado y la app debe mostrar el fallo de impresión sin repetir el cobro.
5. Encender la impresora sin pulsar imprimir: no debe salir ninguna copia sola.
   Recuperar manualmente el intento y verificar una sola copia, sin otra operación.
6. Desconectar/reconectar y reiniciar la app. Confirmar que sigue seleccionada,
   que puede reimprimir y que el teclado manual y lector continúan funcionando.

Registrar tamaño probado, modelo, conexión, foto de la salida, resultado de
reimpresión/error/recuperación y cantidades antes/después. No marcar ambos anchos
como probados físicamente si la impresora solo usa uno. Si el modelo no acepta
el protocolo de impresión utilizado, conservar cobros y registrar la adaptación
específica de transporte/comandos antes de aceptar esa impresora.

## Confirmación del usuario — 3 de noviembre de 2026

El usuario informó que las pruebas físicas de tickets desde Android salieron bien. Señaló como pendiente que no se imprimía el logo. Esto confirma su aceptación del ticket físico existente; no se han informado modelo ni ancho concretos, por lo que no se atribuye aceptación física independiente a ambos anchos.

Se encontró que el generador ESC/POS solo enviaba texto de cabecera. Se incorporó el logo oficial SB incluido en assets/images/logo.png (mismo SHA-256 que la marca pública) a la cabecera compartida de venta, apartado, abono y ticket de prueba. La salida gráfica nueva debe confirmarse con un ticket de prueba en esa impresora después de actualizar la APK. No requiere repetir cobros ni crear operaciones para comprobarlo.

La corrección se entrega en APK 1.0.2 / 2026110301, con logo compacto tras la observación del usuario sobre su tamaño. Simulación final de ambos anchos aprobada. Pendiente exclusivamente confirmar el logo gráfico nuevo en la impresora del cliente usando «Ticket prueba», después de actualizar.

## Cierre de aceptación — 4 de noviembre de 2026

El usuario confirmó que probó la nueva APK 1.0.2 y que el logo salió bien. Queda aceptada la salida física del logo compacto y cerrada la prueba pendiente de impresora de tickets de esta etapa. La confirmación no especifica modelo ni ancho; los dos tamaños 58/80 mm cuentan con simulación, sin atribuir prueba física de ambos.

Estado actual y resumen de validaciones: [Cierre de etapa](STAGE_CLOSURE_2026-11-04.md). Las instrucciones y pendientes anteriores se conservan como historial, no como tareas abiertas.
