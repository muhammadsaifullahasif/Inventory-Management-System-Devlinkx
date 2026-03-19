<?php

use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the group account already exists
        $existingGroup = ChartOfAccount::where('code', '1300')->first();

        if (!$existingGroup) {
            // Create the Accounts Receivable group account
            $group = ChartOfAccount::create([
                'code' => '1300',
                'name' => 'Accounts Receivable',
                'nature' => 'asset',
                'type' => 'group',
                'is_system' => true,
                'is_active' => true,
                'opening_balance' => 0,
                'current_balance' => 0,
            ]);

            // Create Trade Receivables child account
            ChartOfAccount::firstOrCreate(
                ['code' => '1301'],
                [
                    'parent_id' => $group->id,
                    'code' => '1301',
                    'name' => 'Trade Receivables',
                    'nature' => 'asset',
                    'type' => 'account',
                    'is_system' => true,
                    'is_active' => true,
                    'opening_balance' => 0,
                    'current_balance' => 0,
                ]
            );

            // Create Marketplace Receivables child account
            ChartOfAccount::firstOrCreate(
                ['code' => '1302'],
                [
                    'parent_id' => $group->id,
                    'code' => '1302',
                    'name' => 'Marketplace Receivables',
                    'nature' => 'asset',
                    'type' => 'account',
                    'is_system' => false,
                    'is_active' => true,
                    'opening_balance' => 0,
                    'current_balance' => 0,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete in reverse order (children first)
        ChartOfAccount::where('code', '1302')->delete();
        ChartOfAccount::where('code', '1301')->delete();
        ChartOfAccount::where('code', '1300')->delete();
    }
};
