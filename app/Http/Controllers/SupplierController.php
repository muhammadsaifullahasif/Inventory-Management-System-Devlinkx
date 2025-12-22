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
    public function index()
    {
        $suppliers = Supplier::orderBy('created_at', 'DESC')->paginate(25);
        return view('suppliers.index', compact('suppliers'));
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
            'last_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'address_line_1' => 'required',
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
            'last_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'address_line_1' => 'required',
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
}
