<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:journal-entries-view');
    }

    /**
     * List all journal entries
     */
    public function index(Request $request)
    {
        $query = JournalEntry::with(['lines.account', 'createdBy']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'like', "%{$search}%")
                    ->orWhere('narration', 'like', "%{$search}%");
            });
        }

        // Type filter
        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->where('entry_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('entry_date', '<=', $request->date_to);
        }

        $entries = $query->orderBy('entry_date', 'DESC')
                        ->orderBy('id', 'DESC')
                        ->paginate(15)
                        ->withQueryString();

        return view('journal-entries.index', compact('entries'));
    }

    /**
     * Show journal entry details
     */
    public function show(JournalEntry $journalEntry)
    {
        $journalEntry->load(['lines.account', 'createdBy']);

        // Get the source document
        $reference = $journalEntry->getReference();

        return view('journal-entries.show', compact('journalEntry', 'reference'));
    }
}
