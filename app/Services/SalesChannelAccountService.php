<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesChannelAccountService
{
    // Parent group codes
    const BANKS_GROUP_CODE = '1000';           // Banks group for channel bank accounts
    const SALES_GROUP_CODE = '4000';           // Sales group for revenue

    /**
     * Create accounting accounts for a sales channel
     * Creates:
     * - Bank account under Banks (1000) - e.g., "eBay Store Bank"
     * - Sales account under Sales (4000) - e.g., "eBay Store Sales"
     *
     * @param SalesChannel $salesChannel
     * @return bool
     */
    public function createAccountsForChannel(SalesChannel $salesChannel): bool
    {
        try {
            return DB::transaction(function () use ($salesChannel) {
                // Create bank account under Banks (1000)
                $bankAccount = $this->createBankAccount($salesChannel);

                // Create sales account under Sales (4000)
                $salesAccount = $this->createSalesAccount($salesChannel);

                if ($bankAccount && $salesAccount) {
                    $salesChannel->update([
                        'receivable_account_id' => $bankAccount->id,
                        'sales_account_id' => $salesAccount->id,
                    ]);

                    Log::info('Created accounting accounts for sales channel', [
                        'sales_channel_id' => $salesChannel->id,
                        'sales_channel_name' => $salesChannel->name,
                        'bank_account_id' => $bankAccount->id,
                        'bank_account_code' => $bankAccount->code,
                        'sales_account_id' => $salesAccount->id,
                        'sales_account_code' => $salesAccount->code,
                    ]);

                    return true;
                }

                return false;
            });
        } catch (\Exception $e) {
            Log::error('Failed to create accounting accounts for sales channel', [
                'sales_channel_id' => $salesChannel->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create bank account for a sales channel under Banks (1000)
     */
    protected function createBankAccount(SalesChannel $salesChannel): ?ChartOfAccount
    {
        $parentGroup = ChartOfAccount::where('code', self::BANKS_GROUP_CODE)->first();

        if (!$parentGroup) {
            Log::warning('Banks group not found', ['code' => self::BANKS_GROUP_CODE]);
            return null;
        }

        // Generate unique code
        $code = $this->generateNextCode(self::BANKS_GROUP_CODE);

        // Check if account already exists for this channel
        $existingAccount = ChartOfAccount::where('name', $salesChannel->name . ' Bank')
            ->where('parent_id', $parentGroup->id)
            ->first();

        if ($existingAccount) {
            return $existingAccount;
        }

        return ChartOfAccount::create([
            'parent_id' => $parentGroup->id,
            'code' => $code,
            'name' => $salesChannel->name . ' Bank',
            'nature' => 'asset',
            'type' => 'account',
            'is_system' => false,
            'is_active' => true,
            'is_bank_cash' => true,
            'bank_name' => $salesChannel->name,
            'description' => "Bank account for {$salesChannel->name} sales channel payments",
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);
    }

    /**
     * Create sales account for a sales channel under Sales (4000)
     */
    protected function createSalesAccount(SalesChannel $salesChannel): ?ChartOfAccount
    {
        $parentGroup = ChartOfAccount::where('code', self::SALES_GROUP_CODE)->first();

        if (!$parentGroup) {
            Log::warning('Sales group not found', ['code' => self::SALES_GROUP_CODE]);
            return null;
        }

        // Generate unique code
        $code = $this->generateNextCode(self::SALES_GROUP_CODE);

        // Check if account already exists for this channel
        $existingAccount = ChartOfAccount::where('name', $salesChannel->name . ' Sales')
            ->where('parent_id', $parentGroup->id)
            ->first();

        if ($existingAccount) {
            return $existingAccount;
        }

        return ChartOfAccount::create([
            'parent_id' => $parentGroup->id,
            'code' => $code,
            'name' => $salesChannel->name . ' Sales',
            'nature' => 'revenue',
            'type' => 'account',
            'is_system' => false,
            'is_active' => true,
            'description' => "Sales revenue account for {$salesChannel->name} sales channel",
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);
    }

    /**
     * Generate the next available account code under a parent group
     */
    protected function generateNextCode(string $parentCode): string
    {
        $parentGroup = ChartOfAccount::where('code', $parentCode)->first();

        if (!$parentGroup) {
            return $parentCode . '99';
        }

        // Get the highest code among children
        $maxCode = ChartOfAccount::where('parent_id', $parentGroup->id)
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')
            ->value('code');

        if ($maxCode) {
            // Increment the last code
            $nextCode = (int) $maxCode + 1;
            return (string) $nextCode;
        }

        // If no children, start with parent code + 01
        return $parentCode[0] . str_pad((int) substr($parentCode, 1) + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Delete accounting accounts for a sales channel
     */
    public function deleteAccountsForChannel(SalesChannel $salesChannel): bool
    {
        try {
            // Check if accounts have any journal entries
            if ($salesChannel->receivable_account_id) {
                $bankAccount = ChartOfAccount::find($salesChannel->receivable_account_id);
                if ($bankAccount && $bankAccount->journalLines()->exists()) {
                    Log::warning('Cannot delete bank account - has journal entries', [
                        'account_id' => $salesChannel->receivable_account_id,
                    ]);
                    return false;
                }
            }

            if ($salesChannel->sales_account_id) {
                $salesAccount = ChartOfAccount::find($salesChannel->sales_account_id);
                if ($salesAccount && $salesAccount->journalLines()->exists()) {
                    Log::warning('Cannot delete sales account - has journal entries', [
                        'account_id' => $salesChannel->sales_account_id,
                    ]);
                    return false;
                }
            }

            // Delete the accounts
            if ($salesChannel->receivable_account_id) {
                ChartOfAccount::destroy($salesChannel->receivable_account_id);
            }

            if ($salesChannel->sales_account_id) {
                ChartOfAccount::destroy($salesChannel->sales_account_id);
            }

            // Clear the references
            $salesChannel->update([
                'receivable_account_id' => null,
                'sales_account_id' => null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete accounting accounts for sales channel', [
                'sales_channel_id' => $salesChannel->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
