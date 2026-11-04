<?php

return [
    // Existing objects retain their disk; switching this only affects new uploads.
    'product_disk' => env('PRODUCT_IMAGES_DISK', 'public'),
    'customer_photo_disk' => env('CUSTOMER_PHOTOS_DISK', 'local'),
    'carousel_disk' => env('CAROUSEL_IMAGES_DISK', 'public'),
    // Activate only after media:migrate-brand-logo verifies the immutable key.
    'brand_logo_disk' => env('BRAND_LOGO_DISK'),
    'brand_logo_path' => env('BRAND_LOGO_PATH'),
    'migration_enabled' => (bool) env('MEDIA_MIGRATION_ENABLED', false),
    'migration_approval_file' => env('MEDIA_MIGRATION_APPROVAL_FILE'),
    'migration_source_backup' => env('MEDIA_MIGRATION_SOURCE_BACKUP'),
    'retention_days' => 15,
];
