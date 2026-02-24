<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('sold_out_at')->nullable()->after('sold_at')->index();
            $table->decimal('sale_price_base', 10, 2)->nullable()->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sold_out_at']);
            $table->dropColumn(['sold_out_at', 'sale_price_base']);
        });
    }
};
