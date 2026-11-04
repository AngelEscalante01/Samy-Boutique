<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make(\App\Services\ProductionMediaMigrationGuard::class)->validate();
$scan = json_decode(file_get_contents('/migration/orientation.json'), true, 512, JSON_THROW_ON_ERROR);
$candidates = $scan['repair_candidates'];
$ids = array_column($candidates, 'image_id');
foreach ($ids as $id) if (! is_int($id) || $id < 1) throw new RuntimeException('Invalid repair ID');
$images = \App\Models\ProductImage::query()->whereNull('deleted_at')->whereIn('id', $ids)->with('derivatives')->get();
if ($images->count() !== count($ids)) throw new RuntimeException('Image inventory changed');
$oldPaths = $images->flatMap(fn ($image) => $image->derivatives->pluck('path'))->all();
$originals = $images->mapWithKeys(fn ($image) => [$image->id => [$image->path, $image->sha256, $image->bytes]])->all();
if ($ids) {
    $options = ['--execute' => true, '--refresh-orientation' => true, '--id' => $ids, '--limit' => 1000];
    if (\Illuminate\Support\Facades\Artisan::call('images:backfill-derivatives', $options) !== 0) {
        throw new RuntimeException('Orientation repair failed: '.\Illuminate\Support\Facades\Artisan::output());
    }
    $fingerprint = fn () => \App\Models\ProductImage::query()->whereIn('id', $ids)->with('derivatives')->orderBy('id')->get()
        ->map(fn ($image) => [$image->id, $image->generation, $image->derivatives->sortBy('kind')->map(fn ($version) => [$version->id, $version->path, $version->sha256])->values()->all()])->all();
    $first = $fingerprint();
    if (\Illuminate\Support\Facades\Artisan::call('images:backfill-derivatives', $options) !== 0 || $first !== $fingerprint()) {
        throw new RuntimeException('Repair replay changed references');
    }
}
$verified = 0;
foreach ($candidates as $candidate) {
    $image = \App\Models\ProductImage::query()->with('derivatives')->findOrFail($candidate['image_id']);
    if ([$image->path, $image->sha256, $image->bytes] !== $originals[$image->id]) throw new RuntimeException('Original reference changed');
    $app->make(\App\Services\ProductImageStorage::class)->verify('r2_public', $image->path, $image->sha256, $image->bytes);
    [$width, $height] = [$candidate['width'], $candidate['height']];
    if ($candidate['orientation'] >= 5) [$width, $height] = [$height, $width];
    foreach ($image->derivatives as $version) {
        $bytes = \Illuminate\Support\Facades\Storage::disk($version->disk)->get($version->path);
        $size = getimagesizefromstring($bytes);
        $edge = $version->kind === 'thumbnail' ? 480 : 1600;
        $scale = min(1.0, $edge / max($width, $height));
        if (!$size || [$size[0], $size[1]] !== [(int) round($width * $scale), (int) round($height * $scale)]
            || strlen($bytes) !== $version->bytes || hash('sha256', $bytes) !== $version->sha256) {
            throw new RuntimeException('Oriented copy failed readback verification');
        }
        $verified++;
    }
}
foreach ($oldPaths as $path) if (! \Illuminate\Support\Facades\Storage::disk('r2_public')->exists($path)) throw new RuntimeException('Old copy was removed');
echo json_encode(['images_checked' => count($ids), 'oriented_derivatives_verified' => $verified,
    'originals_unchanged' => true, 'old_objects_preserved' => true, 'repeat_did_not_change_references' => true,
    'commercial_operations_created' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
