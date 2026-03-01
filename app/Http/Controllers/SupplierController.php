<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view suppliers'), ['only' => ['index']]);
        $this->middleware(PermissionMiddleware::using('add suppliers'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit suppliers'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete suppliers'), ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Supplier::query();

        // Filter by search term (name, email, company)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('active_status')) {
            $query->where('active_status', $request->active_status);
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        $perPage = $request->input('per_page', 25);
        $suppliers = $query->orderBy('created_at', 'DESC')->paginate($perPage)->withQueryString();
        return view('suppliers.index', compact('suppliers', 'perPage'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('suppliers.new');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'active_status' => 'required',
        ]);

        try {
            $supplier = new Supplier();
            $supplier->first_name = $request->first_name;
            $supplier->last_name = $request->last_name;
            $supplier->email = $request->email;
            $supplier->phone = $request->phone;
            $supplier->company = $request->company;
            $supplier->designation = $request->designation;
            $supplier->address_line_1 = $request->address_line_1;
            $supplier->address_line_2 = $request->address_line_2;
            $supplier->country = $request->country;
            $supplier->state = $request->state;
            $supplier->city = $request->city;
            $supplier->zipcode = $request->zipcode;
            $supplier->active_status = $request->active_status;
            $supplier->save();

            return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInputs($request->all());
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
        $supplier = Supplier::findOrFail($id);

        return view('suppliers.edit', compact('supplier'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'first_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'active_status' => 'required',
        ]);

        try {
            $supplier = Supplier::findOrFail($id);
            $supplier->first_name = $request->first_name;
            $supplier->last_name = $request->last_name;
            $supplier->email = $request->email;
            $supplier->phone = $request->phone;
            $supplier->company = $request->company;
            $supplier->designation = $request->designation;
            $supplier->address_line_1 = $request->address_line_1;
            $supplier->address_line_2 = $request->address_line_2;
            $supplier->country = $request->country;
            $supplier->state = $request->state;
            $supplier->city = $request->city;
            $supplier->zipcode = $request->zipcode;
            $supplier->active_status = $request->active_status;
            $supplier->save();

            return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInputs($request->all());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            $supplier->delete();

            return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully.');
        } catch (\Throwable $th) {
            return back()->with('error', 'Brand not deleted.');
        }
    }

    /**
     * Bulk delete suppliers
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:suppliers,id',
        ]);

        try {
            $count = Supplier::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => $count . ' supplier(s) deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting suppliers: ' . $e->getMessage(),
            ], 500);
        }
    }
}
