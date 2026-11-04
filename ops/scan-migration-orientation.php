<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make(\App\Services\ProductionMediaMigrationGuard::class)->validate();
$service = $app->make(\App\Services\ProductImageDerivatives::class);
$candidates = [];
$count = 0;
foreach (\App\Models\ProductImage::query()->whereNull('deleted_at')->where('disk', 'r2_public')->cursor() as $image) {
    $path = \Illuminate\Support\Facades\Storage::disk('public')->path($image->source_path);
    if (! is_file($path)) throw new RuntimeException('Frozen original missing');
    $orientation = $service->orientation($path);
    $count++;
    if ($orientation !== 1) {
        $size = getimagesize($path);
        $candidates[] = ['image_id' => $image->id, 'orientation' => $orientation, 'width' => $size[0], 'height' => $size[1]];
    }
}
echo json_encode(['active_images_scanned' => $count, 'repair_candidates' => $candidates], JSON_THROW_ON_ERROR).PHP_EOL;
