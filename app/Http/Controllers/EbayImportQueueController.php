<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use App\Jobs\ImportEbayListings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EbayImportQueueController extends Controller
{
    /**
     * Dispatch job to queue for importing active listings
     */
    public function dispatchActiveListingsJob(string $id)
    {
        try {
            $salesChannel = SalesChannel::findOrFail($id);

            // Dispatch job to queue
            ImportEbayListings::dispatch($id, 'active')
                ->onQueue('default');

            Log::info('eBay active listings import job dispatched', [
                'sales_channel_id' => $id,
            ]);

            return redirect()->back()->with('success', 'Import job has been queued. The import will start shortly.');
        } catch (\Exception $e) {
            Log::error('Failed to dispatch import job', [
                'sales_channel_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to queue the import job: ' . $e->getMessage());
        }
    }

    /**
     * Dispatch job to queue for importing unsold listings
     */
    public function dispatchUnsoldListingsJob(string $id)
    {
        try {
            $salesChannel = SalesChannel::findOrFail($id);

            // Dispatch job to queue
            ImportEbayListings::dispatch($id, 'unsold')
                ->onQueue('default');

            Log::info('eBay unsold listings import job dispatched', [
                'sales_channel_id' => $id,
            ]);

            return redirect()->back()->with('success', 'Unsold listings import job has been queued. The import will start shortly.');
        } catch (\Exception $e) {
            Log::error('Failed to dispatch unsold listings job', [
                'sales_channel_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to queue the import job: ' . $e->getMessage());
        }
    }

    /**
     * Dispatch job with delay
     */
    public function dispatchWithDelay(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'job_type' => 'required|in:active,unsold',
                'delay_minutes' => 'required|integer|min:1|max:1440', // Max 24 hours
            ]);

            $salesChannel = SalesChannel::findOrFail($id);

            // Dispatch job with delay
            ImportEbayListings::dispatch($id, $validated['job_type'])
                ->delay(now()->addMinutes($validated['delay_minutes']))
                ->onQueue('default');

            Log::info('eBay import job dispatched with delay', [
                'sales_channel_id' => $id,
                'job_type' => $validated['job_type'],
                'delay_minutes' => $validated['delay_minutes'],
            ]);

            return redirect()->back()->with('success', "Import job scheduled for {$validated['delay_minutes']} minutes from now.");
        } catch (\Exception $e) {
            Log::error('Failed to dispatch delayed import job', [
                'sales_channel_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to queue the import job: ' . $e->getMessage());
        }
    }

    /**
     * Get queue status (if you have a dashboard)
     */
    public function queueStatus()
    {
        // This is a simple example - you might want to use a package like laravel-horizon
        return view('ebay.queue-status');
    }
}
