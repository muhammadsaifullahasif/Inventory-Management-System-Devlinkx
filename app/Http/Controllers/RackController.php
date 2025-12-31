<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Warehouse;
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
    public function index()
    {
        $racks = Rack::orderBy('created_at', 'DESC')->paginate(25);
        return view('racks.index', compact('racks'));
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
}
