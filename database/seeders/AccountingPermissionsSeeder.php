<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
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
            'chart-of-accounts-view',
            'chart-of-accounts-add',
            'chart-of-accounts-edit',
            'chart-of-accounts-delete',
            
            // Bills
            'bills-view',
            'bills-add',
            'bills-edit',
            'bills-delete',
            'bills-post',

            // Payments
            'payments-view',
            'payments-add',
            'payments-delete',

            // Journal Entries
            'journal-entries-view',

            // General Ledger
            'general-ledger-view',

            // Reports
            'accounting-reports-view',
            'accounting-reports-export',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
