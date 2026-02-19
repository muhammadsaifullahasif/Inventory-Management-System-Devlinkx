<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class BasicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Orders
            'view orders',
            'add orders',
            'edit orders',
            'delete orders',
            
            // Products
            'view products',
            'add products',
            'edit products',
            'delete products',

            // Categories
            'view categories',
            'add categories',
            'edit categories',
            'delete categories',

            // Brands
            'view brands',
            'add brands',
            'edit brands',
            'delete brands',

            // Purchases
            'view purchases',
            'add purchases',
            'edit purchases',
            'delete purchases',

            // Warehouses
            'view warehouses',
            'add warehouses',
            'edit warehouses',
            'delete warehouses',

            // Racks
            'view racks',
            'add racks',
            'edit racks',
            'delete racks',

            // Sales Channels
            'view sales-channels',
            'add sales-channels',
            'edit sales-channels',
            'delete sales-channels',

            // Suppliers
            'view suppliers',
            'add suppliers',
            'edit suppliers',
            'delete suppliers',

            // Shipping
            'view shipping',
            'add shipping',
            'edit shipping',
            'delete shipping',

            // Payments
            'view users',
            'add users',
            'edit users',
            'delete users',

            // Roles
            'view roles',
            'add roles',
            'edit roles',
            'delete roles',

            // Permissions
            'view permissions',
            'add permissions',
            'edit permissions',
            'delete permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role = Role::create(['name' => 'superadmin']);
        $roleAdmin = Role::create(['name' => 'Admin']);

        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                $roleAdmin->givePermissionTo($permission);
            }
        }

        $user = new User();
        $user->name = 'Super Admin';
        $user->email = 'superadmin@gmail.com';
        $user->password = Hash::make('12345678');
        $user->save();
        
        $userAdmin = new User();
        $userAdmin->name = 'Admin';
        $userAdmin->email = 'admin@gmail.com';
        $userAdmin->password = Hash::make('12345678');
        $userAdmin->save();
        $userAdmin->syncRoles('Admin');
    }
}
