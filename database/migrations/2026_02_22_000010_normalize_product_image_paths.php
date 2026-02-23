<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_images')
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->where('path', 'not like', '%/%')
            ->update([
                'path' => DB::raw("CONCAT('products/', path)"),
            ]);
    }

    public function down(): void
    {
        DB::table('product_images')
            ->where('path', 'like', 'products/%')
            ->whereRaw("path NOT LIKE 'products/%/%'")
            ->update([
                'path' => DB::raw("SUBSTRING(path, 10)"),
            ]);
    }
};
