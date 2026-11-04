<?php
// Synthetic public image only; does not boot Laravel or access a database.
require '/app/vendor/autoload.php';
if (getenv('R2_PUBLIC_BUCKET') !== 'samy-public-prod') {
    throw new RuntimeException('Exact public bucket required');
}
$image = imagecreatetruecolor(32, 32);
imagefill($image, 0, 0, imagecolorallocate($image, 245, 195, 55));
ob_start();
imagepng($image);
$bytes = ob_get_clean();
imagedestroy($image);
$key = 'samy/products/0/00000000-0000-4000-8000-202611010001.png';
$s3 = new \Aws\S3\S3Client(['version' => 'latest', 'region' => 'auto', 'endpoint' => getenv('R2_ENDPOINT'),
    'use_path_style_endpoint' => false, 'credentials' => ['key' => getenv('R2_ACCESS_KEY_ID'), 'secret' => getenv('R2_SECRET_ACCESS_KEY')]]);
$s3->putObject(['Bucket' => 'samy-public-prod', 'Key' => $key, 'Body' => $bytes, 'ContentType' => 'image/png']);
echo json_encode(['synthetic_only' => true, 'url' => 'https://imagenes.samyboutique.com.mx/'.$key,
    'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'database_accessed' => false]).PHP_EOL;
