<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDatabaseName() !== 'samy_notifications_qa_legacy_20261010') {
    throw new RuntimeException('Refusing to inspect any non-isolated database.');
}

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert(DB::table('layaways')->count() === 28, 'Layaway count changed.');
$assert(DB::table('layaways')->whereNull('fecha_vencimiento')->count() === 4, 'Null date count changed.');
$assert(DB::table('layaways')->where('status', 'open')->count() === 2, 'Open statuses changed.');
$assert(DB::table('layaways')->where('status', 'liquidated')->count() === 17, 'Liquidated statuses changed.');
$assert(DB::table('layaways')->where('status', 'cancelled')->count() === 9, 'Cancelled statuses changed.');
$assert(DB::table('layaways')->where('id', 1)->value('fecha_vencimiento') === '2026-10-09', 'Original due date changed.');
$assert(DB::table('layaways')->where('id', 2)->value('fecha_vencimiento') === '2026-11-01', 'Future due date changed.');
$assert(! Schema::hasColumn('layaways', 'expires_at'), 'Redundant expires_at column exists.');
$assert(Schema::hasColumn('layaways', 'observaciones'), 'Observations column is missing.');
$assert(DB::table('layaway_payments')->where('layaway_id', 1)->value('amount') == 25, 'Payment changed.');
$assert(DB::table('sales')->where('id', 1)->value('total') == 250, 'Sale changed.');
$assert(DB::table('product_variants')->where('id', 1)->value('stock') === 7, 'Inventory changed.');
foreach (['system_notifications', 'notification_preferences', 'notification_devices', 'notification_dispatches', 'notification_announcements', 'notification_inventory_states', 'notification_deliveries', 'notification_delivery_attempts'] as $table) {
    $assert(Schema::hasTable($table), "Missing notification table {$table}.");
}
$assert(DB::table('system_notifications')->count() === 0, 'Migration emitted notifications.');
echo "LAYAWAYS_SCHEMA_COMPAT_PASS: 28 anonymous legacy records and all dates/payments/sales/stock preserved.\n";
