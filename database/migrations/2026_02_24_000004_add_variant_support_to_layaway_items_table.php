<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layaway_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->integer('qty')->default(1)->after('quantity');

            $table->index('product_variant_id');
        });

        DB::table('layaway_items')->update([
            'qty' => DB::raw('COALESCE(quantity, 1)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('layaway_items', function (Blueprint $table) {
            $table->dropIndex(['product_variant_id']);
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('qty');
        });
    }
};
