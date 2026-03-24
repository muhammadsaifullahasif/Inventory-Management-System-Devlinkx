<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBankBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:sync-bank-balances
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all bank/cash account balances from journal entries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║           BANK BALANCE SYNC COMMAND                          ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->info('');
        }

        // Get all bank/cash accounts
        $bankAccounts = ChartOfAccount::where('is_bank_cash', true)->get();

        if ($bankAccounts->isEmpty()) {
            $this->info('No bank/cash accounts found.');
            return 0;
        }

        $this->info("Found {$bankAccounts->count()} bank/cash account(s)");
        $this->info('');

        $updated = 0;
        $rows = [];

        foreach ($bankAccounts as $account) {
            // Calculate balance from journal entries
            $calculatedBalance = $account->getCalculatedBalance();
            $currentBalance = (float) $account->current_balance;
            $difference = $calculatedBalance - $currentBalance;

            $rows[] = [
                $account->code,
                $account->name,
                '$' . number_format($currentBalance, 2),
                '$' . number_format($calculatedBalance, 2),
                $difference != 0 ? '$' . number_format($difference, 2) : '-',
            ];

            // Update if there's a difference
            if (abs($difference) > 0.001 && !$dryRun) {
                $account->current_balance = $calculatedBalance;
                $account->save();
                $updated++;
            } elseif (abs($difference) > 0.001) {
                $updated++;
            }
        }

        $this->table(
            ['Code', 'Account Name', 'Stored Balance', 'Calculated Balance', 'Difference'],
            $rows
        );

        $this->info('');

        if ($dryRun) {
            $this->warn('═══════════════════════════════════════════════════════════════');
            $this->warn("  {$updated} account(s) would be updated.");
            $this->warn('  This was a DRY RUN. No changes were made.');
            $this->warn('  Run without --dry-run to apply changes.');
            $this->warn('═══════════════════════════════════════════════════════════════');
        } else {
            $this->info('═══════════════════════════════════════════════════════════════');
            $this->info("  ✅ {$updated} account balance(s) updated successfully!");
            $this->info('═══════════════════════════════════════════════════════════════');
        }

        return 0;
    }
}
