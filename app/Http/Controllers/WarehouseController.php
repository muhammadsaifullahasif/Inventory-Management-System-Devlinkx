<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

class WarehouseController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view warehouses'), ['only' => ['index']]);
        $this->middleware(PermissionMiddleware::using('add warehouses'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit warehouses'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete warehouses'), ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Warehouse::with('racks');

        // Filter by search term (name)
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Filter by default status
        if ($request->filled('is_default')) {
            $query->where('is_default', $request->is_default);
        }

        $warehouses = $query->orderBy('created_at', 'DESC')->paginate(25)->withQueryString();
        return view('warehouses.index', compact('warehouses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('warehouses.new');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'nullable|boolean',
            'racks' => 'nullable|integer|min:0',
            'rack' => 'nullable|array',
            'rack.*' => 'nullable|string|max:255',
        ]);

        try {
            $warehouse = Warehouse::create([
                'name' => $request->name,
                'is_default' => $request->is_default ? '1' : '0'
            ]);

            // Handle racks creation
            if ($request->has('rack') && is_array($request->rack)) {
                foreach ($request->rack as $rackName) {
                    if (!empty($rackName)) {
                        $warehouse->racks()->create([
                            'name' => $rackName,
                        ]);
                    }
                }
            }

            if ($request->is_default) {
                // Unset other warehouses as default
                Warehouse::where('id', '!=', Warehouse::latest()->first()->id)
                    ->update(['is_default' => '0']);
            }

            return redirect()->route('warehouses.index')->with('success', 'Warehouse created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the warehouse: ' . $e->getMessage())->withInput();
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
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $warehouse = Warehouse::with('racks')->findOrFail($id);
        return view('warehouses.edit', compact('warehouse'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'nullable|boolean',
            'racks' => 'nullable|integer|min:0',
            'rack' => 'nullable|array',
            'rack.*' => 'nullable|string|max:255',
            'rack_id' => 'nullable|array',
            'rack_id.*' => 'nullable|integer',
        ]);


        try {
            $warehouse = Warehouse::findOrFail($id);
            $warehouse->name = $request->name;
            $warehouse->is_default = $request->is_default ? '1' : '0';
            $warehouse->save();

            // Handle racks update
            if ($request->has('rack') && is_array($request->rack)) {
                $rackIds = $request->rack_id ?? [];
                $existingRackIds = [];

                foreach ($request->rack as $index => $rackName) {
                    if (!empty($rackName)) {
                        $rackId = $rackIds[$index] ?? null;

                        if ($rackId && $rackId != '') {
                            // Update existing rack
                            $rack = $warehouse->racks()->find($rackId);
                            if ($rack) {
                                $rack->update(['name' => $rackName]);
                                $existingRackIds[] = $rackId;
                            }
                        } else {
                            // Create new rack
                            $newRack = $warehouse->racks()->create(['name' => $rackName]);
                            $existingRackIds[] = $newRack->id;
                        }
                    }
                }

                // Delete racks that were removed
                $warehouse->racks()->whereNotIn('id', $existingRackIds)->delete();
            }

            if ($request->is_default) {
                // Unset other warehouses as default
                Warehouse::where('id', '!=', $warehouse->id)
                    ->update(['is_default' => '0']);
            }

            return redirect()->route('warehouses.index')->with('success', 'Warehouse updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating the warehouse: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $warehouse = Warehouse::findOrFail($id);
            $warehouse->delete();

            return redirect()->route('warehouses.index')->with('success', 'Warehouse deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the warehouse: ' . $e->getMessage());
        }
    }
}
