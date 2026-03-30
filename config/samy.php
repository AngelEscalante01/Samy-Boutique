<?php

return [
    // Si está activo, al vender se borran archivos físicos (disk public) y registros product_images.
    'delete_images_on_sale' => env('SAMY_DELETE_IMAGES_ON_SALE', false),

    // Umbrales para distinguir descuento "básico" vs "alto".
    // - Si un descuento percent supera max_percent => requiere sales.apply_discount_high
    // - Si un descuento amount supera max_amount => requiere sales.apply_discount_high
    'discount_basic_max_percent' => env('SAMY_DISCOUNT_BASIC_MAX_PERCENT', 10),
    'discount_basic_max_amount' => env('SAMY_DISCOUNT_BASIC_MAX_AMOUNT', 100),

    // Número para CTA de WhatsApp en catálogo público (formato internacional sin +).
    'catalog_whatsapp_number' => env('SAMY_CATALOG_WHATSAPP_NUMBER', ''),

    // Ruta temporal para correr migraciones desde web (desactivada por defecto).
    'temp_migration_route_enabled' => env('SAMY_TEMP_MIGRATION_ROUTE_ENABLED', false),
    'temp_migration_route_token' => env('SAMY_TEMP_MIGRATION_ROUTE_TOKEN', ''),
];
