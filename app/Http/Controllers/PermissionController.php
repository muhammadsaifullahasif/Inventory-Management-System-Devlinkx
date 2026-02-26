<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view permissions'), ['only' => ['index']]);
        $this->middleware(PermissionMiddleware::using('add permissions'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit permissions'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete permissions'), ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Permission::query();

        // Filter by search term (name)
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Filter by category
        if ($request->filled('category')) {
            if ($request->category === 'uncategorized') {
                $query->whereNull('category')->orWhere('category', '');
            } else {
                $query->where('category', $request->category);
            }
        }

        $permissions = $query->orderBy('category')->orderBy('name')->paginate(25)->withQueryString();

        // Get predefined categories from model
        $categories = Permission::getCategories();

        return view('permissions.index', compact('permissions', 'categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get predefined categories from model
        $categories = Permission::getCategories();

        return view('permissions.new', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name',
            'category' => 'nullable|string|max:255'
        ]);

        try {
            Permission::create([
                'name' => $request->name,
                'category' => $request->category ?: null
            ]);

            return redirect()->route('permissions.index')->with('success', 'Permission added successfully.');
        } catch (\Throwable $th) {
            return back()->with('error', 'Permission not added.')->withInput();
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
        $permission = Permission::findOrFail($id);

        // Get predefined categories from model
        $categories = Permission::getCategories();

        return view('permissions.edit', compact('permission', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name,' . $id,
            'category' => 'nullable|string|max:255'
        ]);

        try {
            $permission = Permission::findOrFail($id);
            $permission->name = $request->name;
            $permission->category = $request->category ?: null;
            $permission->save();

            return redirect()->route('permissions.index')->with('success', 'Permission updated successfully.');
        } catch (\Throwable $th) {
            return back()->with('error', 'Permission not updated.')->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $permission = Permission::findOrFail($id);
            $permission->delete();

            return redirect()->route('permissions.index')->with('success', 'Permission deleted successfully.');
        } catch (\Throwable $th) {
            return back()->with('error', 'Permission not deleted.');
        }
    }
}
