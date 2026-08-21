<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ModulePermissionsSeeder extends Seeder
{
    /**
     * Adds permissions for modules that had none (System Health, Backups,
     * Market Research, Returns), then grants them to the Admin role.
     * Superadmin bypasses all permission checks via Gate::before, so it
     * doesn't need explicit grants.
     */
    public function run(): void
    {
        $permissions = [
            // Market Research
            ['name' => 'view market-research', 'category' => 'Market Research'],
            ['name' => 'export market-research', 'category' => 'Market Research'],

            // System Health
            ['name' => 'view system-health', 'category' => 'System Health'],
            ['name' => 'manage system-health', 'category' => 'System Health'],

            // Backups
            ['name' => 'view backups', 'category' => 'Backups'],
            ['name' => 'create backups', 'category' => 'Backups'],
            ['name' => 'delete backups', 'category' => 'Backups'],
            ['name' => 'manage backup-settings', 'category' => 'Backups'],

            // Returns
            ['name' => 'add returns', 'category' => 'Orders'],
            ['name' => 'manage returns', 'category' => 'Orders'],
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
