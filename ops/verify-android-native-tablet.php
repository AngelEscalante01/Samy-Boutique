<?php

// Read-only verification of the synthetic Galaxy Tab acceptance fixture.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== '/tmp/samy-android-qa.sqlite') {
    throw new RuntimeException('Solo se permite la base temporal Android QA.');
}

$checks = [];
$check = function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
};
$customers = App\Models\Customer::where('phone', '5500001029')->get();
$check('offline_customer_created_once', $customers->count() === 1
    && $customers->first()->name === 'QA Tablet Offline 20261029');
$mixed = App\Models\Sale::whereHas('payments', fn ($q) => $q->where('reference', 'QA-TABLET-MIXTO-20261029'))->get();
$check('mixed_sale_created_once', $mixed->count() === 1 && (string) $mixed->first()->total === '750.00');
$check('mixed_sale_payments_reconcile', $mixed->count() === 1
    && $mixed->first()->payments()->orderBy('method')->get(['method', 'amount'])->toArray() === [
        ['method' => 'card', 'amount' => '450.00'], ['method' => 'cash', 'amount' => '300.00'],
    ]);
$layaway = App\Models\Layaway::findOrFail(2);
$check('uncertain_payment_created_once', $layaway->payments()->where('reference', 'QA-TABLET-LOST-RESPONSE-20261029')->count() === 1
    && $layaway->payments()->count() === 1);
$check('liquidation_and_delivery_reconcile', $layaway->status === 'liquidated'
    && (string) $layaway->total === '250.00' && (string) $layaway->paid_total === '250.00'
    && (string) $layaway->balance === '0.00' && $layaway->delivered_at !== null
    && $layaway->sale?->status === 'completed' && (string) $layaway->sale?->total === '250.00');
// Later tablet sessions may add variants; only these original fixture rows
// establish the stock reconciliation of the October 29 recovery/reprint.
$check('stock_not_duplicated_by_recovery_or_reprint', App\Models\ProductVariant::whereIn('id', [1, 2, 3])->orderBy('id')->pluck('stock')->all() === [16, 18, 1]);
$product = App\Models\Product::where('name', 'QA Producto Tablet 20261029')->first();
$check('native_product_and_variant_created', $product !== null && $product->sku === 'QAT-000001'
    && $product->variants()->where('sku', 'QAT-000002')->exists() && (string) $product->sale_price_base === '199.00');
$check('native_synthetic_photo_uploaded', App\Models\Product::findOrFail(1)->images()->count() === 1);
$check('fresh_excel_completed', Illuminate\Support\Facades\DB::table('catalog_import_groups')->where('status', 'completed')->count() === 1);
$check('stale_excel_rejected', Illuminate\Support\Facades\DB::table('catalog_import_groups')->where('status', 'invalid')->count() === 1);
$check('firebase_notice_opened', App\Models\SystemNotification::where('event_key', 'android-native-tablet-final-v2')->whereNotNull('read_at')->count() === 1);
$result = [
    'environment' => 'samy-android-qa-sqlite',
    'checks' => $checks,
    'sales' => App\Models\Sale::count(),
    'layaways' => App\Models\Layaway::count(),
    'payments' => App\Models\LayawayPayment::count(),
    'operation_receipts' => Illuminate\Support\Facades\DB::table('api_operation_receipts')->count(),
    'stock' => App\Models\ProductVariant::orderBy('id')->pluck('stock')->all(),
    'push_workers_enabled' => config('firebase.push_enabled'),
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit(in_array(false, $checks, true) ? 1 : 0);
