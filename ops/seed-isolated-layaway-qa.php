<?php

// Synthetic, anonymous legacy fixture. Run only with
// DB_DATABASE=samy_notifications_qa_legacy_20261010.

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDatabaseName() !== 'samy_notifications_qa_legacy_20261010') {
    throw new RuntimeException('Refusing to seed any non-isolated database.');
}
if (Schema::hasTable('layaways')) {
    throw new RuntimeException('Legacy QA fixture already exists; refusing to overwrite it.');
}

Schema::create('users', function (Blueprint $table): void {
    $table->id();
});
Schema::create('product_variants', function (Blueprint $table): void {
    $table->id();
    $table->unsignedInteger('stock');
});
Schema::create('sales', function (Blueprint $table): void {
    $table->id();
    $table->decimal('total', 10, 2);
});
Schema::create('layaways', function (Blueprint $table): void {
    $table->id();
    $table->string('status', 20);
    $table->date('fecha_vencimiento')->nullable()->index();
    $table->unsignedInteger('vigencia_dias')->nullable();
    $table->decimal('subtotal', 10, 2);
    $table->decimal('paid_total', 10, 2);
    $table->timestamps();
});
Schema::create('layaway_payments', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('layaway_id');
    $table->decimal('amount', 10, 2);
});

DB::table('users')->insert(['id' => 1]);
DB::table('product_variants')->insert(['id' => 1, 'stock' => 7]);
DB::table('sales')->insert(['id' => 1, 'total' => '250.00']);

for ($id = 1; $id <= 28; $id++) {
    $status = $id <= 2 ? 'open' : ($id <= 19 ? 'liquidated' : 'cancelled');
    $date = match ($id) {
        1 => '2026-10-09',
        2 => '2026-11-01',
        3, 4, 20, 21 => null,
        default => '2026-10-12',
    };
    DB::table('layaways')->insert([
        'id' => $id,
        'status' => $status,
        'fecha_vencimiento' => $date,
        'vigencia_dias' => $date === null ? null : 30,
        'subtotal' => '100.00',
        'paid_total' => $status === 'liquidated' ? '100.00' : '25.00',
        'created_at' => '2026-09-01 12:00:00',
        'updated_at' => '2026-09-01 12:00:00',
    ]);
}
DB::table('layaway_payments')->insert(['id' => 1, 'layaway_id' => 1, 'amount' => '25.00']);
echo 'Synthetic 28-row legacy fixture created without customer data.\n';
