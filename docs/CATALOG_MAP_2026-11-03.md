# Mapa real de Google Maps en el catálogo — 3 de noviembre de 2026

El usuario aclaró que desea un mapa visible, no un icono. Se sustituyó la franja anterior por dirección y CTA a la izquierda y un iframe oficial de Google Maps a la derecha en escritorio; en móvil se apilan dirección, CTA y mapa. El mapa aparece bajo el carrusel y antes de los productos.

El src se obtuvo desde la ficha original proporcionada por el usuario, Compartir > Incorporar un mapa. Identifica Samy Boutique, CID 4036288571028570754, coordenadas 18.177315, -93.0629967. Google mantiene sus controles, atribución, pin con nombre y opción Abrir en Maps; no se cubren con overlays. iframe con título accesible, carga diferida, HTTPS y referrerpolicy strict-origin-when-cross-origin. CTA conserva el enlace corto original, target _blank, noopener noreferrer y flecha SVG. No requiere clave nueva de API.

Validación: npm run build local aprobado; imágenes de producción app/gateway compiladas con la configuración frontend existente. Comparación de la fuente revisada r9 contra el archivo nuevo excluyendo la sección de ubicación: cero diferencias fuera de ese bloque. Publicación samy-release-app/gateway:20261103-r10. Fuente, manifiesto y entorno anteriores guardados en private-audit/catalog-map-before-20261103; fuente de recuperación y manifiesto actualizados.

Comprobado en el catálogo público: mapa cargado con calles y pin Samy Boutique tanto en escritorio como en una visita móvil nueva de 390 × 844. En móvil: documento 386 px, iframe 353 × 256, sin desborde horizontal; mapa por debajo de la dirección. La comprobación móvil se realizó con recarga después de cambiar el viewport; el iframe diferido no se había reinicializado durante el cambio de emulación de escritorio a móvil. Se restauró el viewport normal. Catálogo y login HTTPS 200; seis servicios de Samy activos. Sin cambios de datos, migraciones ni operaciones comerciales.

Captura final: docs/evidence/catalog-map-2026-11-03/production-mobile.png. Esta implementación sustituye la decisión anterior de mostrar solo icono/enlace documentada el 2 de noviembre.
