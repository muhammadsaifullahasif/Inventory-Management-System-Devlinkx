<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // ==================== ASSET ACCOUNTS ====================
            [
                'code' => '1000',
                'name' => 'Banks',
                'nature' => 'asset',
                'type' => 'group',
                'is_system' => true,
                'children' => [
                    [
                        'code' => '1001',
                        'name' => 'MCB Bank',
                        'is_bank_cash' => true,
                        'bank_name' => 'MCB',
                    ],
                    [
                        'code' => '1002',
                        'name' => 'UBL Bank',
                        'is_bank_cash' => true,
                        'bank_name' => 'UBL',
                    ],
                    [
                        'code' => '1003',
                        'name' => 'HBL Bank',
                        'is_bank_cash' => true,
                        'bank_name' => 'HBL',
                    ],
                ],
            ],
            [
                'code' => '1100',
                'name' => 'Cash in Hand',
                'nature' => 'asset',
                'type' => 'group',
                'is_system' => true,
                'children' => [
                    [
                        'code' => '1101',
                        'name' => 'Petty Cash',
                        'is_bank_cash' => true,
                    ],
                    [
                        'code' => '1102',
                        'name' => 'Main Cash',
                        'is_bank_cash' => true,
                    ],
                ],
            ],
            [
                'code' => '1200',
                'name' => 'Inventory',
                'nature' => 'asset',
                'type' => 'group',
                'is_system' => true,
                'children' => [
                    ['code' => '1201', 'name' => 'Stock in Hand'],
                ],
            ],

            // ==================== LIABILITY ACCOUNTS ====================
            [
                'code' => '2000',
                'name' => 'Accounts Payable',
                'nature' => 'liability',
                'type' => 'group',
                'is_system' => true,
                'children' => [
                    [
                        'code' => '2001',
                        'name' => 'Trade Payables',
                        'is_system' => true,
                    ],
                ],
            ],

            // ==================== REVENUE ACCOUNTS ====================
            [
                'code' => '4000',
                'name' => 'Sales',
                'nature' => 'revenue',
                'type' => 'group',
                'is_system' => true,
                'children' => [
                    ['code' => '4001', 'name' => 'Product Sales'],
                    ['code' => '4002', 'name' => 'Service Revenue'],
                ],
            ],

            // ==================== EXPENSE ACCOUNTS ====================
            [
                'code' => '5000',
                'name' => 'Cost of Sales',
                'nature' => 'expense',
                'type' => 'group',
                'is_system' => true,
                'children' => [
                    ['code' => '5001', 'name' => 'Inventory Cost'],
                    ['code' => '5002', 'name' => 'Freight Charges'],
                    ['code' => '5003', 'name' => 'Duties & Customs'],
                    ['code' => '5004', 'name' => 'Other Direct Costs'],
                ],
            ],
            [
                'code' => '6000',
                'name' => 'Administrative Expenses',
                'nature' => 'expense',
                'type' => 'group',
                'is_system' => true,
                'children' => [
                    ['code' => '6001', 'name' => 'Rent Expense'],
                    ['code' => '6002', 'name' => 'Salaries & Wages'],
                    ['code' => '6003', 'name' => 'Utilities'],
                    ['code' => '6004', 'name' => 'Office Supplies'],
                    ['code' => '6005', 'name' => 'Internet & Telephone'],
                    ['code' => '6006', 'name' => 'Insurance'],
                    ['code' => '6007', 'name' => 'Repairs & Maintenance'],
                ],
            ],
            [
                'code' => '7000',
                'name' => 'Selling Expenses',
                'nature' => 'expense',
                'type' => 'group',
                'is_system' => true,
                'children' => [
                    ['code' => '7001', 'name' => 'Marketing & Advertising'],
                    ['code' => '7002', 'name' => 'Sales Commission'],
                    ['code' => '7003', 'name' => 'Shipping & Delivery'],
                ],
            ],
            [
                'code' => '8000',
                'name' => 'Financial Charges',
                'nature' => 'expense',
                'type' => 'group',
                'is_system' => true,
                'children' => [
                    ['code' => '8001', 'name' => 'Bank Charges'],
                    ['code' => '8002', 'name' => 'Interest Expense'],
                    ['code' => '8003', 'name' => 'Exchange Loss'],
                ],
            ],
        ];

        foreach ($accounts as $group) {
            $children = $group['children'] ?? [];
            unset($group['children']);

            // Create parent group
            $parent = ChartOfAccount::firstOrCreate(
                ['code' => $group['code']], // Search by code
                $group // Create with these values if not found
            );

            // Create child accounts
            foreach ($children as $child) {
                ChartOfAccount::firstOrCreate(
                    ['code' => $child['code']], // Search by code
                    [
                        'parent_id' => $parent->id,
                        'code' => $child['code'],
                        'name' => $child['name'],
                        'nature' => $parent->nature,
                        'type' => 'account',
                        'is_system' => $child['is_system'] ?? false,
                        'is_active' => true,
                        'is_bank_cash' => $child['is_bank_cash'] ?? false,
                        'bank_name' => $child['bank_name'] ?? null,
                        'opening_balance' => 0,
                        'current_balance' => 0,
                    ]
                );
            }
        }
    }
}
