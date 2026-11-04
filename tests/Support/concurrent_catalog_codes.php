<?php

// One-off MariaDB integration probe. Run only against a dedicated synthetic DB.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Services\CatalogCodeService;
use Illuminate\Support\Facades\DB;

$category = DB::transaction(function () {
    $category = Category::create(['name' => 'Concurrency smoke', 'active' => true]);
    app(CatalogCodeService::class)->setPrefix($category, 'SMK');

    return $category;
});

$workers = 8;
$children = [];
for ($i = 0; $i < $workers; $i++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        fwrite(STDERR, "Cannot fork worker.\n");
        exit(1);
    }
    if ($pid === 0) {
        DB::disconnect();
        try {
            DB::transaction(function () use ($category) {
                app(CatalogCodeService::class)->next($category->id, 'product');
                usleep(150000); // Keep the lock long enough for overlap.
            }, 10);
            exit(0);
        } catch (Throwable $error) {
            fwrite(STDERR, $error->getMessage()."\n");
            exit(1);
        }
    }
    $children[] = $pid;
}

$failed = false;
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
    $failed = $failed || ! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0;
}
$codes = DB::table('catalog_codes')->where('code', 'like', 'SMK-%')->pluck('code')->all();
$unique = count(array_unique($codes));
printf("workers=%d issued=%d unique=%d failed=%s\n", $workers, count($codes), $unique, $failed ? 'yes' : 'no');
exit($failed || count($codes) !== $workers || $unique !== $workers ? 1 : 0);
