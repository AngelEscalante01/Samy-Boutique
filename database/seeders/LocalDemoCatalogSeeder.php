<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use App\Services\CatalogCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LocalDemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Este catálogo de ejemplo solo se puede cargar en local.');
        }

        DB::transaction(function (): void {
            $category = Category::query()->firstOrCreate(['name' => 'Vestidos'], ['active' => true]);
            $category->update(['active' => true]);
            app(CatalogCodeService::class)->setPrefix($category, 'VES');

            foreach (['M', 'L'] as $name) {
                Size::query()->updateOrCreate(['name' => $name], ['active' => true]);
            }
            foreach (['Negro' => '#000000', 'Beige' => '#D6C5A4'] as $name => $hex) {
                Color::query()->updateOrCreate(['name' => $name], ['hex' => $hex, 'active' => true]);
            }

            Category::query()->where('name', 'SmokeCategory')->update(['active' => false]);
            Size::query()->where('name', 'SmokeSize')->update(['active' => false]);
            Color::query()->where('name', 'SmokeColor')->update(['active' => false]);
        });
    }
}
