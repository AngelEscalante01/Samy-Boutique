<?php
// Private backup cache. Does not boot Laravel, alter a DB or delete remote media.
require '/app/vendor/autoload.php';
if (getenv('R2_PUBLIC_BUCKET') !== 'samy-public-prod' || getenv('R2_PRIVATE_BUCKET') !== 'samy-private-prod') {
    throw new RuntimeException('Exact Samy buckets required');
}
try {
    $synthetic = in_array('--synthetic-only', $argv, true);
    $s3 = new \Aws\S3\S3Client(['version' => 'latest', 'region' => 'auto', 'endpoint' => getenv('R2_ENDPOINT'),
        'use_path_style_endpoint' => false, 'credentials' => ['key' => getenv('R2_ACCESS_KEY_ID'), 'secret' => getenv('R2_SECRET_ACCESS_KEY')]]);
    $count = 0;
    $downloaded = 0;
    foreach (['public', 'private'] as $kind) {
        $root = '/snapshot/'.$kind;
        if (! is_dir($root)) mkdir($root, 0700, true);
        if (! is_dir($root.'/objects')) mkdir($root.'/objects', 0700, true);
        $manifestPath = $root.'/manifest.json';
        $previous = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR) : [];
        $manifest = [];
        foreach ($s3->getPaginator('ListObjectsV2', ['Bucket' => getenv('R2_'.strtoupper($kind).'_BUCKET'),
            'Prefix' => $synthetic ? 'samy/release-check/' : '']) as $page) {
            foreach ($page['Contents'] ?? [] as $item) {
                $key = $item['Key'];
                if (str_starts_with($key, 'samy/backups/')) continue;
                $relative = 'objects/'.hash('sha256', $key);
                $path = $root.'/'.$relative;
                $modified = (string) $item['LastModified'];
                $cached = $previous[$key] ?? null;
                if (! $cached || ! is_file($path) || filesize($path) !== (int) $item['Size']
                    || $cached['modified'] !== $modified || $cached['etag'] !== $item['ETag']
                    || ! hash_equals($cached['sha256'], hash_file('sha256', $path))) {
                    $temp = $path.'.part';
                    $s3->getObject(['Bucket' => getenv('R2_'.strtoupper($kind).'_BUCKET'), 'Key' => $key, 'SaveAs' => $temp]);
                    if (filesize($temp) !== (int) $item['Size']) throw new RuntimeException('Snapshot object size mismatch');
                    rename($temp, $path);
                    chmod($path, 0600);
                    $downloaded++;
                }
                $manifest[$key] = ['file' => $relative, 'bytes' => (int) $item['Size'], 'sha256' => hash_file('sha256', $path),
                    'modified' => $modified, 'etag' => (string) $item['ETag']];
                $count++;
            }
        }
        file_put_contents($manifestPath.'.part', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        rename($manifestPath.'.part', $manifestPath);
        chmod($manifestPath, 0600);
    }
    echo json_encode(['objects' => $count, 'downloaded' => $downloaded, 'synthetic_only' => $synthetic,
        'remote_deletions' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (\Throwable $error) {
    echo json_encode(['passed' => false, 'error_type' => get_class($error)]).PHP_EOL;
    exit(1);
}
