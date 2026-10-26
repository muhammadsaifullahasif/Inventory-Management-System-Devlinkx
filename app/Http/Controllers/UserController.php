<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Middleware\PermissionMiddleware;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view users'), ['only' => ['index', 'show']]);
        $this->middleware(PermissionMiddleware::using('add users'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit users'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete users'), ['only' => ['destroy', 'bulkDelete']]);
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Filter by search term (name, email)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('id', $request->role);
            });
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
        $users = $query->paginate($perPage)->withQueryString();
        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles', 'perPage'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = $this->assignableRoles();
        return view('users.new', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',
        ]);

        $this->authorizeRoleAssignment($request->role);

        try {
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->save();

            $user->syncRoles($request->role);

            return redirect()->route('users.index')->with('success', 'User created successfully');
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
        $user = User::findOrFail($id);
        $this->authorizeManageUser($user);
        $roles = $this->assignableRoles();
        $hasRoles = $user->roles->pluck('id');
        return view('users.edit', compact('user', 'roles', 'hasRoles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$id.',id',
        ]);

        $user = User::findOrFail($id);
        $this->authorizeManageUser($user);
        $this->authorizeRoleAssignment($request->role);

        try {
            $passwordChanged = !empty($request->password);
            $rolesBefore = $user->getRoleNames()->sort()->values()->all();

            $user->name = $request->name;
            $user->email = $request->email;
            if ($passwordChanged) {
                $user->password = Hash::make($request->password);
            }

            app(\App\Services\AuditLogger::class)->withEvent(
                $passwordChanged ? 'password_reset' : 'profile_updated',
                $user,
                fn () => $user->save()
            );

            $user->syncRoles($request->role);
            $rolesAfter = $user->getRoleNames()->sort()->values()->all();

            if ($rolesBefore !== $rolesAfter) {
                app(\App\Services\AuditLogger::class)->log('role_changed', $user,
                    ['roles' => $rolesBefore],
                    ['roles' => $rolesAfter]
                );
            }

            return redirect()->route('users.index')->with('success', 'User updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $this->authorizeManageUser($user);

        try {
            $user->delete();

            return redirect()->route('users.index')->with('success', 'User deleted successfully.');
        } catch (\Throwable $th) {
            return back()->with('error', 'User not deleted.');
        }
    }

    /**
     * Bulk delete users
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:users,id',
        ]);

        $users = User::whereIn('id', $request->ids)->get();
        if ($users->contains(fn (User $user) => !auth()->user()->canManageUser($user))) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete users who have access you do not have.',
            ], 403);
        }

        try {
            $count = User::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => $count . ' user(s) deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting users: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Roles the current user may hand out: never superadmin (unless they are
     * one), and never a role carrying permissions they don't hold themselves.
     */
    private function assignableRoles()
    {
        return Role::with('permissions')->orderBy('name', 'DESC')->get()
            ->filter(fn (Role $role) => auth()->user()->canManageRole($role))
            ->values();
    }

    private function authorizeRoleAssignment($roleNames): void
    {
        $requested = collect($roleNames)->filter()->values();
        $allowed = $this->assignableRoles()->pluck('name');

        abort_if($requested->diff($allowed)->isNotEmpty(), 403, 'You cannot assign a role with access you do not have.');
    }

    private function authorizeManageUser(User $user): void
    {
        abort_unless(auth()->user()->canManageUser($user), 403, 'You cannot manage a user who has access you do not have.');
    }
}
