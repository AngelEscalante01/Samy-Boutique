<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->integer('qty')->default(1)->after('quantity');
            $table->decimal('discount', 10, 2)->default(0)->after('discount_amount');
            $table->decimal('final_price', 10, 2)->nullable()->after('line_total');

            $table->index('product_variant_id');
        });

        DB::table('sale_items')->update([
            'qty' => DB::raw('COALESCE(quantity, 1)'),
            'discount' => DB::raw('COALESCE(discount_amount, 0)'),
            'final_price' => DB::raw('COALESCE(line_total, unit_price)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['product_variant_id']);
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn(['qty', 'discount', 'final_price']);
        });
    }
};
