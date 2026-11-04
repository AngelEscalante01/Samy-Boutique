<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $path = DB::getDriverName() === 'sqlite'
            ? "'products/' || path"
            : "CONCAT('products/', path)";
        DB::table('product_images')
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->where('path', 'not like', '%/%')
            ->update([
                'path' => DB::raw($path),
            ]);
    }

    public function down(): void
    {
        $path = DB::getDriverName() === 'sqlite'
            ? 'substr(path, 10)'
            : 'SUBSTRING(path, 10)';
        DB::table('product_images')
            ->where('path', 'like', 'products/%')
            ->whereRaw("path NOT LIKE 'products/%/%'")
            ->update([
                'path' => DB::raw($path),
            ]);
    }
};
