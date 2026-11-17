<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class MessagePermissionsSeeder extends Seeder
{
    /**
     * Permissions for the Messages inbox, granted to the Admin role.
     * Superadmin bypasses all checks via Gate::before.
     *
     * Existing installs: php artisan db:seed --class=MessagePermissionsSeeder
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'view messages', 'category' => 'Messages'],
            ['name' => 'reply messages', 'category' => 'Messages'],
            ['name' => 'assign messages', 'category' => 'Messages'],
            ['name' => 'manage message-settings', 'category' => 'Messages'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['category' => $permission['category']]
            );
        }

        $roleAdmin = Role::firstOrCreate(['name' => 'Admin']);

        foreach ($permissions as $permission) {
            $roleAdmin->givePermissionTo($permission['name']);
        }
    }
}
