<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->index('sale_id', 'sale_items_sale_id_idx');
            $table->dropUnique(['sale_id', 'product_id']);
            $table->unique(['sale_id', 'product_variant_id']);
        });

        Schema::table('layaway_items', function (Blueprint $table) {
            $table->index('layaway_id', 'layaway_items_layaway_id_idx');
            $table->dropUnique(['layaway_id', 'product_id']);
            $table->unique(['layaway_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropUnique(['sale_id', 'product_variant_id']);
            $table->unique(['sale_id', 'product_id']);
            $table->dropIndex('sale_items_sale_id_idx');
        });

        Schema::table('layaway_items', function (Blueprint $table) {
            $table->dropUnique(['layaway_id', 'product_variant_id']);
            $table->unique(['layaway_id', 'product_id']);
            $table->dropIndex('layaway_items_layaway_id_idx');
        });
    }
};
