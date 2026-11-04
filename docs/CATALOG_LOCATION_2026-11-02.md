# Ubicación debajo del carrusel — 2 de noviembre de 2026

Se añadió únicamente una franja de ubicación entre el carrusel y el contenido del catálogo público, en resources/js/Pages/Public/Catalog/Index.vue. Pin SVG, dirección pública de la ficha enlazada y acceso «Cómo llegar». Todo el bloque es un enlace accesible al URL proporcionado por el usuario, con target _blank y rel noopener noreferrer. Dirección contrastada en Google Maps: C. Carl.G.Mendez 10, San Luis, 86200 Jalpa de Méndez, Tabasco.

Tras la observación del usuario se sustituyó la flecha Unicode, que algunos dispositivos mostraban como emoji azul, por SVG de trazo fino con currentColor. Móvil usa texto y flecha sin recuadro; escritorio añade botón. No se carga un iframe ni se necesita una clave de mapas.

Compilación local y de producción aprobadas. Comprobación local en escritorio y 390 × 844, sin recorte de dirección; producción HTTPS muestra el bloque bajo el carrusel. Se pulsó el enlace publicado y abrió Google Maps en la ficha Samy Boutique, coordenadas 18.177315, -93.0629967. Comprobación DOM de producción: tres SVG y ninguna flecha emoji en el bloque, ancho 1536 y documento 1532. Catálogo y login responden 200.

Se comparó el componente con la fuente revisada del release anterior, retirando el bloque añadido: ninguna diferencia restante. Imágenes app/gateway samy-release-*:20261102-r8, compiladas con las variables frontend existentes de producción. Conservados entorno, fuente y manifiesto anteriores en private-audit/catalog-location-before-20261102, fuera del repositorio. Fuente de recuperación y manifiesto actualizados. Despliegue limitado a los servicios de Samy; sin migraciones ni escrituras comerciales. Evidencia visual en docs/evidence/catalog-location-2026-11-02.

## Ajuste del foco visual — revisión r9

El usuario observó que el marco rosa alrededor de toda la franja parecía extraño. Se eliminó ese marco visible y se trasladó el indicador de teclado al elemento «Cómo llegar» (botón en escritorio y texto en móvil), manteniendo el enlace completo accesible. Dirección, pin, SVG y destino conservados.

Publicada samy-release-app/gateway:20261102-r9 tras compilar con configuración existente. Verificado en producción con foco de teclado real: :focus-visible activo, outline de la franja transparente y anillo únicamente en el botón. Comprobada también vista sin foco; captura production-no-frame.png. Copia previa, fuente y manifiesto preservados/actualizados como en el despliegue anterior. Sin cambios de datos.
