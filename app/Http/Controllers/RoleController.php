<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Middleware\PermissionMiddleware;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view roles'), ['only' => ['index', 'show']]);
        $this->middleware(PermissionMiddleware::using('add roles'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit roles'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete roles'), ['only' => ['destroy', 'bulkDelete']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Role::with('permissions');

        // Filter by search term (name)
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 25);
        $roles = $query->paginate($perPage)->withQueryString();
        return view('roles.index', compact('roles', 'perPage'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = $this->grantablePermissions();
        return view('roles.new', compact('permissions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
        ]);

        $this->authorizePermissionGrant($request->permission);

        try {
            $role = Role::create(['name' => $request->name]);

            if (!empty($request->permission)) {
                foreach ($request->permission as $permission) {
                    $role->givePermissionTo($permission);
                }
            }

            return redirect()->route('roles.index')->with('success', 'Role created successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
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
        $role = Role::findOrFail($id);
        $this->authorizeManageRole($role);
        $permissions = $this->grantablePermissions();
        $hasPermissions = $role->permissions->pluck('name');
        return view('roles.edit', compact('role', 'permissions', 'hasPermissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|unique:roles,name,'.$id
        ]);

        $role = Role::findOrFail($id);
        $this->authorizeManageRole($role);
        $this->authorizePermissionGrant($request->permission);

        try {
            $role->name = $request->name;
            $role->save();

            if (!empty($request->permission)) {
                $role->syncPermissions($request->permission);
            } else {
                $role->syncPermissions([]);
            }

            return redirect()->route('roles.index')->with('success', 'Role updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::findOrFail($id);
        $this->authorizeManageRole($role);

        try {
            $role->delete();

            return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
        } catch (\Throwable $th) {
            return back()->with('error', 'Role not deleted.');
        }
    }

    /**
     * Bulk delete roles
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:roles,id',
        ]);

        $roles = Role::with('permissions')->whereIn('id', $request->ids)->get();
        if ($roles->contains(fn (Role $role) => !auth()->user()->canManageRole($role))) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete roles with access you do not have.',
            ], 403);
        }

        try {
            $count = Role::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => $count . ' role(s) deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting roles: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permissions the current user may put on a role: only ones they hold.
     */
    private function grantablePermissions()
    {
        return Permission::orderBy('created_at', 'DESC')->get()
            ->filter(fn (Permission $permission) => auth()->user()->holdsAllPermissions([$permission->name]))
            ->values();
    }

    private function authorizePermissionGrant($permissionNames): void
    {
        abort_unless(
            auth()->user()->holdsAllPermissions(collect($permissionNames)->filter()),
            403,
            'You cannot grant a permission you do not have.'
        );
    }

    private function authorizeManageRole(Role $role): void
    {
        abort_unless(auth()->user()->canManageRole($role), 403, 'You cannot manage a role with access you do not have.');
    }
}
