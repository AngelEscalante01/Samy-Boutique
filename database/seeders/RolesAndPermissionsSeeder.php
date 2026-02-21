<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed roles and permissions for Samy Boutique.
     */
    public function run(): void
    {
        // Reset cached roles/permissions so changes apply immediately.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // POS / ventas
            'pos.view',
            'sales.view',
            'sales.create',
            'sales.apply_coupon',
            'sales.apply_discount_basic',
            'sales.apply_discount_high',
            'sales.cancel',

            // Apartados
            'layaways.view',
            'layaways.create',
            'layaways.pay',
            'layaways.cancel',

            // Clientes
            'customers.view',
            'customers.create',
            'customers.update',

            // Productos
            'products.view',
            'products.create',
            'products.update',
            'products.delete_images',
            'products.view_purchase_price',

            // Catálogos / cupones / reportes / cortes / usuarios
            'catalogs.manage',
            'coupons.manage',
            'settings.manage',
            'reports.view',
            'cash_cuts.create',
            'users.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, guardName: 'web');
        }

        $managerRole = Role::findOrCreate('gerente', guardName: 'web');
        $cashierRole = Role::findOrCreate('cajero', guardName: 'web');

        // Gerente: acceso total
        $managerRole->syncPermissions($permissions);

        // Cajero: permisos limitados (operación diaria)
        $cashierRole->syncPermissions([
            'pos.view',
            'sales.view',
            'sales.create',
            'sales.apply_coupon',
            'sales.apply_discount_basic',
            'sales.cancel',

            'layaways.view',
            'layaways.create',
            'layaways.pay',

            'customers.view',
            'customers.create',
            'customers.update',

            'products.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
