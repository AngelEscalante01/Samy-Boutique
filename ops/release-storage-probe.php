<?php
// Synthetic storage checks only. No Laravel/database boot and no business records.
require '/app/vendor/autoload.php';

if (getenv('R2_PUBLIC_BUCKET') !== 'samy-public-prod' || getenv('R2_PRIVATE_BUCKET') !== 'samy-private-prod'
    || getenv('R2_PUBLIC_URL') !== 'https://imagenes.samyboutique.com.mx') {
    throw new RuntimeException('Exact Samy production destinations required');
}
try {
    $s3 = new \Aws\S3\S3Client([
        'version' => 'latest', 'region' => 'auto', 'endpoint' => getenv('R2_ENDPOINT'),
        'use_path_style_endpoint' => false,
        'credentials' => ['key' => getenv('R2_ACCESS_KEY_ID'), 'secret' => getenv('R2_SECRET_ACCESS_KEY')],
    ]);
    $key = 'samy/release-check/20261101/connectivity.txt';
    $content = 'Synthetic Samy release storage check 20261101';
    $result = [];
    foreach (['public', 'private'] as $kind) {
        $bucket = getenv('R2_'.strtoupper($kind).'_BUCKET');
        $s3->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => $content, 'ContentType' => 'text/plain']);
        $body = (string) $s3->getObject(['Bucket' => $bucket, 'Key' => $key])['Body'];
        $result[$kind.'_sha256_verified'] = hash_equals(hash('sha256', $content), hash('sha256', $body));
    }
    $signed = (string) $s3->createPresignedRequest($s3->getCommand('GetObject', [
        'Bucket' => getenv('R2_PRIVATE_BUCKET'), 'Key' => $key,
    ]), '+2 minutes')->getUri();
    $http = new \GuzzleHttp\Client(['timeout' => 20, 'http_errors' => false]);
    $result['private_signed_status'] = $http->get($signed)->getStatusCode();
    $result['private_unsigned_status'] = $http->get(strtok($signed, '?'))->getStatusCode();
    $response = $http->get(getenv('R2_PUBLIC_URL').'/'.$key);
    $result['public_https_status'] = $response->getStatusCode();
    $result['public_sha256_verified'] = hash_equals(hash('sha256', $content), hash('sha256', (string) $response->getBody()));
    $result['tls_validation_enabled'] = true;
    echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
    exit($result['public_sha256_verified'] && $result['private_sha256_verified']
        && $result['public_https_status'] === 200 && $result['private_signed_status'] === 200
        && in_array($result['private_unsigned_status'], [400, 401, 403], true) ? 0 : 1);
} catch (\Throwable $error) {
    // Provider exceptions can contain signed URLs; keep them off the console.
    echo json_encode(['passed' => false, 'error_type' => get_class($error)]).PHP_EOL;
    exit(1);
}
