<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDatabaseName() !== 'samy_notifications_qa_devlegacy_20261010') {
    throw new RuntimeException('Refusing to touch any non-isolated database.');
}

if (($argv[1] ?? '') === 'before') {
    if (Schema::hasTable('layaways')) {
        throw new RuntimeException('DEV fixture already exists; refusing to overwrite.');
    }
    Schema::create('layaways', function (Blueprint $table): void {
        $table->id();
        $table->string('status', 20);
        $table->decimal('subtotal', 10, 2);
        $table->decimal('paid_total', 10, 2);
        $table->timestamp('expires_at')->nullable()->index();
        $table->timestamps();
    });
    DB::table('layaways')->insert([
        'status' => 'open', 'subtotal' => '100.00',
        'paid_total' => '0.00', 'expires_at' => '2026-10-17 23:59:59',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    echo "Isolated old DEV row seeded.\n";
} elseif (($argv[1] ?? '') === 'after') {
    $row = DB::table('layaways')->first();
    if (! $row || $row->fecha_vencimiento !== '2026-10-17'
        || $row->expires_at !== '2026-10-17 23:59:59'
        || ! Schema::hasColumn('layaways', 'observaciones')
        || ! Schema::hasColumn('layaways', 'vigencia_dias')) {
        throw new RuntimeException('DEV compatibility upgrade did not preserve the date and data.');
    }
    echo "Old DEV schema upgraded; date copied and original timestamp preserved.\n";
} else {
    throw new InvalidArgumentException('Usage: before|after');
}
