<?php

// One-off MariaDB integration probe. Never run against a commercial database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Layaway;
use App\Models\User;
use App\Services\LayawayAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

if (DB::connection()->getDatabaseName() !== 'samy_concurrency' || DB::connection()->getDriverName() !== 'mysql') {
    fwrite(STDERR, "Use only the dedicated samy_concurrency MariaDB database.\n");
    exit(1);
}

$user = User::create([
    'name' => 'Refund concurrency smoke',
    'email' => 'refund-smoke-'.bin2hex(random_bytes(5)).'@example.test',
    'password' => 'local-only',
    'active' => true,
]);

$run = static function (bool $sameKey, int $workers) use ($user): array {
    $layaway = Layaway::create([
        'created_by' => $user->id,
        'status' => 'open',
        'subtotal' => '100.00',
        'paid_total' => '300.00',
    ]);
    $children = [];
    for ($i = 0; $i < $workers; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Cannot fork refund worker.');
        }
        if ($pid === 0) {
            DB::disconnect();
            try {
                app(LayawayAdjustmentService::class)->refund(
                    Layaway::findOrFail($layaway->id),
                    [
                        'operation_key' => $sameKey ? 'concurrent-same-refund-12345' : 'concurrent-refund-'.$i.'-12345',
                        'amount' => '150.00',
                        'method' => 'cash',
                        'reason' => 'Synthetic concurrency probe',
                    ],
                    User::findOrFail($user->id),
                );
                exit(0);
            } catch (ValidationException) {
                exit(2);
            } catch (Throwable $error) {
                fwrite(STDERR, $error->getMessage()."\n");
                exit(1);
            }
        }
        $children[] = $pid;
    }

    $statuses = [];
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $statuses[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 1;
    }
    DB::disconnect();

    return [
        'statuses' => $statuses,
        'rows' => $layaway->refunds()->count(),
        'refunded' => $layaway->fresh()->refunded_total,
    ];
};

$same = $run(true, 8);
$different = $run(false, 2);
$ok = $same['rows'] === 1 && $same['refunded'] === '150.00'
    && count(array_filter($same['statuses'], fn ($status) => $status === 0)) === 8
    && $different['rows'] === 1 && $different['refunded'] === '150.00'
    && count(array_filter($different['statuses'], fn ($status) => $status === 0)) === 1
    && count(array_filter($different['statuses'], fn ($status) => $status === 2)) === 1;
printf("same_key=%s different_keys=%s pass=%s\n", json_encode($same), json_encode($different), $ok ? 'yes' : 'no');
exit($ok ? 0 : 1);
