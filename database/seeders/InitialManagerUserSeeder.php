<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialManagerUserSeeder extends Seeder
{
    /**
     * Creates initial users and assigns roles.
     */
    public function run(): void
    {
        $ensureUserWithRole = function (string $email, string $name, string $role): void {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill(['name' => $name])->save();
            }

            // Roles must exist (seed RolesAndPermissionsSeeder first)
            $user->syncRoles([$role]);
        };

        $ensureUserWithRole('gerente@gmail.com', 'Gerente', 'gerente');
        $ensureUserWithRole('cajero@gmail.com', 'Cajero', 'cajero');
    }
}
