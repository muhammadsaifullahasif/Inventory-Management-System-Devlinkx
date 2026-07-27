<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AccountingPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Chart of Accounts
            ['name' => 'chart-of-accounts-view', 'category' => 'Accounting'],
            ['name' => 'chart-of-accounts-add', 'category' => 'Accounting'],
            ['name' => 'chart-of-accounts-edit', 'category' => 'Accounting'],
            ['name' => 'chart-of-accounts-delete', 'category' => 'Accounting'],

            // Bills
            ['name' => 'bills-view', 'category' => 'Accounting'],
            ['name' => 'bills-add', 'category' => 'Accounting'],
            ['name' => 'bills-edit', 'category' => 'Accounting'],
            ['name' => 'bills-delete', 'category' => 'Accounting'],
            ['name' => 'bills-post', 'category' => 'Accounting'],

            // Payments
            ['name' => 'payments-view', 'category' => 'Accounting'],
            ['name' => 'payments-add', 'category' => 'Accounting'],
            ['name' => 'payments-delete', 'category' => 'Accounting'],

            // Journal Entries
            ['name' => 'journal-entries-view', 'category' => 'Accounting'],

            // General Ledger
            ['name' => 'general-ledger-view', 'category' => 'Accounting'],

            // Reports
            ['name' => 'accounting-reports-view', 'category' => 'Accounting'],
            ['name' => 'accounting-reports-export', 'category' => 'Accounting'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['category' => $permission['category']]
            );
        }
    }
}
