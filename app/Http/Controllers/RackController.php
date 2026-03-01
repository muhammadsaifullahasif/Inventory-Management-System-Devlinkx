<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

class RackController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view racks'), ['only' => ['index']]);
        $this->middleware(PermissionMiddleware::using('add racks'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit racks'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete racks'), ['only' => ['destroy']]);
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Rack::with('warehouse')
            ->withSum('rack_stock', 'quantity')
            ->withCount(['rack_stock as products_count' => function ($query) {
                $query->where('quantity', '>', 0);
            }])
            ->withCount(['rack_stock as out_of_stock_count' => function ($query) {
                $query->where('quantity', '<=', 0);
            }]);

        // Filter by search term (name)
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Filter by warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by default status
        if ($request->filled('is_default')) {
            $query->where('is_default', $request->is_default);
        }

        $perPage = $request->input('per_page', 25);
        $racks = $query->orderBy('created_at', 'DESC')->paginate($perPage)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('racks.index', compact('racks', 'warehouses', 'perPage'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $warehouses = Warehouse::all();
        return view('racks.new', compact('warehouses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        try {
            Rack::create([
                'name' => $request->name,
                'warehouse_id' => $request->warehouse_id,
                'is_default' => $request->is_default ? '1' : '0',
            ]);

            if ($request->is_default) {
                // Unset other racks as default in the same warehouse
                Rack::where('warehouse_id', $request->warehouse_id)
                    ->where('id', '!=', Rack::latest()->first()->id)
                    ->update(['is_default' => '0']);
            }

            return redirect()->route('racks.index')->with('success', 'Rack created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the rack: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Get racks by warehouse
     * @return \Illuminate\Http\JsonResponse
     * @param Warehouse $warehouse
     */
    public function getRacksByWarehouse(Warehouse $warehouse)
    {
        $racks = $warehouse->racks()->select('id', 'name', 'is_default')->get();
        return response()->json($racks);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $rack = Rack::findOrFail($id);
        $warehouses = Warehouse::all();
        return view('racks.edit', compact('rack', 'warehouses'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        try {
            $rack = Rack::findOrFail($id);
            $rack->update([
                'name' => $request->name,
                'warehouse_id' => $request->warehouse_id,
                'is_default' => $request->is_default ? '1' : '0',
            ]);

            if ($request->is_default) {
                // Unset other racks as default in the same warehouse
                Rack::where('warehouse_id', $request->warehouse_id)
                    ->where('id', '!=', $rack->id)
                    ->update(['is_default' => '0']);
            }

            return redirect()->route('racks.index')->with('success', 'Rack updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the rack: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $rack = Rack::findOrFail($id);
            $rack->delete();

            return redirect()->route('racks.index')->with('success', 'Rack deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the rack: ' . $e->getMessage());
        }
    }

    /**
     * Show label printing options for the specified rack.
     */
    public function printLabel(string $id)
    {
        $rack = Rack::with('warehouse')->findOrFail($id);
        return view('racks.print-label', compact('rack'));
    }

    /**
     * Generate and download label PDF for the specified rack.
     */
    public function printLabelView(Request $request, string $id)
    {
        $rack = Rack::with('warehouse')->findOrFail($id);
        $quantity = (int) $request->get('quantity', 21);
        $quantity = max(1, min(100, $quantity));
        $columns = (int) $request->get('columns', 3);
        $columns = max(2, min(5, $columns));

        $pdf = Pdf::loadView('racks.label', compact('rack', 'quantity', 'columns'))
            ->setPaper('a4', 'portrait');
        return $pdf->download('rack_label_' . $rack->name . '.pdf');
    }

    /**
     * Show bulk label printing form for racks.
     */
    public function bulkPrintLabelForm()
    {
        $racks = Rack::with('warehouse')->orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();
        return view('racks.bulk-print-label', compact('racks', 'warehouses'));
    }

    /**
     * Generate PDF with labels for multiple racks.
     */
    public function bulkPrintLabel(Request $request)
    {
        $request->validate([
            'racks' => 'required|array|min:1',
            'racks.*.id' => 'required|exists:racks,id',
            'racks.*.quantity' => 'required|integer|min:1|max:100',
            'columns' => 'nullable|integer|min:2|max:5',
        ]);

        $columns = (int) $request->get('columns', 3);
        $columns = max(2, min(5, $columns));

        $racksData = [];
        foreach ($request->racks as $rackInput) {
            $rack = Rack::with('warehouse')->find($rackInput['id']);
            if ($rack) {
                $racksData[] = [
                    'rack' => $rack,
                    'quantity' => (int) $rackInput['quantity'],
                ];
            }
        }

        if (empty($racksData)) {
            return redirect()->back()->with('error', 'No valid racks selected for label printing.');
        }

        $pdf = Pdf::loadView('racks.bulk-label', compact('racksData', 'columns'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('rack_labels_' . date('Y-m-d_H-i-s') . '.pdf');
    }

    /**
     * Bulk delete racks
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:racks,id',
        ]);

        try {
            $count = Rack::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => $count . ' rack(s) deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting racks: ' . $e->getMessage(),
            ], 500);
        }
    }
}
