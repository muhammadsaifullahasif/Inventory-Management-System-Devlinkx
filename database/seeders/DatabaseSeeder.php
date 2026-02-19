<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\AccountingPermissionsSeeder;
use Database\Seeders\BasicSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BasicSeeder::class,
            ChartOfAccountsSeeder::class,
            AccountingPermissionsSeeder::class,
        ]);

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
