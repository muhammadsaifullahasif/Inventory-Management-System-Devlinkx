<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create the default superadmin user (idempotent).
     *
     * Override via .env: SUPERADMIN_NAME, SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD.
     * Change the password after the first login.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $user = User::firstOrCreate(
            ['email' => env('SUPERADMIN_EMAIL', 'superadmin@example.com')],
            [
                'name' => env('SUPERADMIN_NAME', 'Super Admin'),
                'password' => env('SUPERADMIN_PASSWORD', 'password'),
                'active_status' => '1',
            ]
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
