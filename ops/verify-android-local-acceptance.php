<?php

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== '/tmp/samy-android-qa.sqlite') {
    throw new RuntimeException('Solo se permite la base temporal Android QA.');
}
$result = [
    'users' => App\Models\User::count(),
    'customers' => App\Models\Customer::count(),
    'sales' => App\Models\Sale::count(),
    'layaways' => App\Models\Layaway::count(),
    'layaway_payments' => App\Models\LayawayPayment::count(),
    'operation_receipts' => Illuminate\Support\Facades\DB::table('api_operation_receipts')->count(),
    'remaining_stock' => App\Models\ProductVariant::orderBy('id')->pluck('stock')->all(),
    'push_enabled' => config('firebase.push_enabled'),
];
echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
if ($result['users'] !== 2 || $result['customers'] !== 1 || $result['sales'] !== 2
    || $result['layaways'] !== 1 || $result['layaway_payments'] !== 2
    || $result['operation_receipts'] !== 3 || $result['remaining_stock'] !== [19, 19]
    || $result['push_enabled'] !== false) {
    exit(1);
}
