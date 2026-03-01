<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

class BrandController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view brands'), ['only' => ['index']]);
        $this->middleware(PermissionMiddleware::using('add brands'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit brands'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete brands'), ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Brand::query();

        // Filter by search term
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $perPage = $request->input('per_page', 25);
        $brands = $query->orderBy('created_at', 'DESC')->paginate($perPage)->withQueryString();

        return view('brands.index', compact('brands', 'perPage'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('brands.new');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $brand = new Brand();
            $brand->name = $request->name;
            $brand->slug = Str::slug($request->name);
            $brand->save();

            return redirect()->route('brands.index')->with('success', 'Brand created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
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
        $brand = Brand::findOrFail($id);

        return view('brands.edit', compact('brand'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $brand = Brand::findOrFail($id);
            $brand->name = $request->name;
            $brand->slug = Str::slug($request->name);
            $brand->save();

            return redirect()->route('brands.index')->with('success', 'Brand updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $brand = Brand::findOrFail($id);
            $brand->delete();

            return redirect()->route('brands.index')->with('success', 'Brand deleted successfully.');
        } catch (\Throwable $th) {
            return back()->with('error', 'Brand not deleted.');
        }
    }

    /**
     * Bulk delete brands
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:brands,id',
        ]);

        try {
            $count = Brand::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => $count . ' brand(s) deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting brands: ' . $e->getMessage(),
            ], 500);
        }
    }
}
