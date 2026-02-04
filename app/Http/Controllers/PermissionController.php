<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
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

        $permissions = $query->orderBy('id', 'DESC')->paginate(25)->withQueryString();
        return view('permissions.index', compact('permissions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('permissions.new');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name'
        ]);

        try {
            Permission::create([
                'name' => $request->name
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
        return view('permissions.edit', compact('permission'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name,' . $id
        ]);

        try {
            $permission = Permission::findOrFail($id);
            $permission->name = $request->name;
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
