<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Fidelidad: cada 5ta compra (cuando purchases_count==4 antes de vender)
        Setting::set('loyalty.enabled', true);
        // type: percent|amount (equivalente a %|$)
        Setting::set('loyalty.type', 'percent');
        Setting::set('loyalty.value', 10);

        // Limpieza de imágenes de productos vendidos
        Setting::set('sales.delete_images_after_days', 15);
        // Modo: soft (auditoría) | hard (elimina registro)
        Setting::set('sales.delete_images_mode', 'soft');
    }
}
