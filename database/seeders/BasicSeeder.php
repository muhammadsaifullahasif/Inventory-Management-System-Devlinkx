<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Permission;
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
            ['name' => 'view orders', 'category' => 'Orders'],
            ['name' => 'add orders', 'category' => 'Orders'],
            ['name' => 'edit orders', 'category' => 'Orders'],
            ['name' => 'delete orders', 'category' => 'Orders'],

            // Products
            ['name' => 'view products', 'category' => 'Products'],
            ['name' => 'add products', 'category' => 'Products'],
            ['name' => 'edit products', 'category' => 'Products'],
            ['name' => 'delete products', 'category' => 'Products'],

            // Categories
            ['name' => 'view categories', 'category' => 'Products'],
            ['name' => 'add categories', 'category' => 'Products'],
            ['name' => 'edit categories', 'category' => 'Products'],
            ['name' => 'delete categories', 'category' => 'Products'],

            // Brands
            ['name' => 'view brands', 'category' => 'Products'],
            ['name' => 'add brands', 'category' => 'Products'],
            ['name' => 'edit brands', 'category' => 'Products'],
            ['name' => 'delete brands', 'category' => 'Products'],

            // Purchases
            ['name' => 'view purchases', 'category' => 'Purchases'],
            ['name' => 'add purchases', 'category' => 'Purchases'],
            ['name' => 'edit purchases', 'category' => 'Purchases'],
            ['name' => 'delete purchases', 'category' => 'Purchases'],

            // Warehouses
            ['name' => 'view warehouses', 'category' => 'Warehouses'],
            ['name' => 'add warehouses', 'category' => 'Warehouses'],
            ['name' => 'edit warehouses', 'category' => 'Warehouses'],
            ['name' => 'delete warehouses', 'category' => 'Warehouses'],

            // Racks
            ['name' => 'view racks', 'category' => 'Warehouses'],
            ['name' => 'add racks', 'category' => 'Warehouses'],
            ['name' => 'edit racks', 'category' => 'Warehouses'],
            ['name' => 'delete racks', 'category' => 'Warehouses'],

            // Sales Channels
            ['name' => 'view sales-channels', 'category' => 'Sales Channels'],
            ['name' => 'add sales-channels', 'category' => 'Sales Channels'],
            ['name' => 'edit sales-channels', 'category' => 'Sales Channels'],
            ['name' => 'delete sales-channels', 'category' => 'Sales Channels'],

            // Suppliers
            ['name' => 'view suppliers', 'category' => 'Suppliers'],
            ['name' => 'add suppliers', 'category' => 'Suppliers'],
            ['name' => 'edit suppliers', 'category' => 'Suppliers'],
            ['name' => 'delete suppliers', 'category' => 'Suppliers'],

            // Shipping
            ['name' => 'view shipping', 'category' => 'Shipping'],
            ['name' => 'add shipping', 'category' => 'Shipping'],
            ['name' => 'edit shipping', 'category' => 'Shipping'],
            ['name' => 'delete shipping', 'category' => 'Shipping'],

            // Users
            ['name' => 'view users', 'category' => 'Users & Access'],
            ['name' => 'add users', 'category' => 'Users & Access'],
            ['name' => 'edit users', 'category' => 'Users & Access'],
            ['name' => 'delete users', 'category' => 'Users & Access'],

            // Roles
            ['name' => 'view roles', 'category' => 'Users & Access'],
            ['name' => 'add roles', 'category' => 'Users & Access'],
            ['name' => 'edit roles', 'category' => 'Users & Access'],
            ['name' => 'delete roles', 'category' => 'Users & Access'],

            // Permissions
            ['name' => 'view permissions', 'category' => 'Users & Access'],
            ['name' => 'add permissions', 'category' => 'Users & Access'],
            ['name' => 'edit permissions', 'category' => 'Users & Access'],
            ['name' => 'delete permissions', 'category' => 'Users & Access'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['category' => $permission['category']]
            );
        }

        $role = Role::create(['name' => 'superadmin']);
        $roleAdmin = Role::create(['name' => 'Admin']);

        foreach ($permissions as $permission) {
            $roleAdmin->givePermissionTo($permission['name']);
        }

        $user = new User();
        $user->name = 'Super Admin';
        $user->email = 'superadmin@gmail.com';
        $user->password = Hash::make('12345678');
        $user->save();
        $user->syncRoles('superadmin');
        
        $userAdmin = new User();
        $userAdmin->name = 'Admin';
        $userAdmin->email = 'admin@gmail.com';
        $userAdmin->password = Hash::make('12345678');
        $userAdmin->save();
        $userAdmin->syncRoles('Admin');


        $categories = [
            'Body Moldings & Trims',
            'Grilles',
            'Fenders',
            'Headlight Assemblies',
            'Bumper Inserts & Covers',
            'Bumpers & Reinforcements',
            'Hinges, Latches & Additional Hood Components',
            'Exterior Locks & Lock Hardware',
            'Tail Light Assemblies',
            'Radiators',
            'Air Filter Housings',
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category, 'slug' => Str::slug($category)]);
        }

        $brands = [
            'Audi',
            'BMW',
            'Cadillac',
            'Chevrolet',
            'Dodge',
            'Ford',
            'GMC',
            'Honda',
            'Hyundai',
            'Jeep',
            'Lexus',
            'Mazda',
            'Mercedes',
            'Mitsubishi',
            'Porsche',
            'Tesla',
            'Toyota',
            'Volkswagen',
        ];

        foreach ($brands as $brand) {
            Brand::firstOrCreate(['name' => $brand, 'slug' => Str::slug($brand)]);
        }

        $warehouse = Warehouse::firstOrCreate(['name' => 'Default Warehouse', 'is_default' => '1']);

        $warehouse->racks()->create([
            'name' => 'Rack 1',
            'is_default' => '1',
        ]);
    }
}
