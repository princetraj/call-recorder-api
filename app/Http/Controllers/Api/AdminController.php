<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Display a listing of admins.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Admin::with('branch');

        // Filter by admin_role
        if ($request->has('admin_role')) {
            $query->where('admin_role', $request->admin_role);
        }

        // Filter by branch_id
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $admins = $query->get();

        return response()->json([
            'success' => true,
            'data' => $admins,
        ], 200);
    }

    /**
     * Store a newly created admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:6',
            'admin_role' => ['required', Rule::in(['super_admin', 'manager', 'trainee'])],
            'branch_id' => 'nullable|exists:branches,id',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'admin_role' => $request->admin_role,
            'branch_id' => $request->branch_id,
            'status' => $request->status ?? 'active',
        ]);

        $admin->load('branch');

        return response()->json([
            'success' => true,
            'message' => 'Admin created successfully',
            'data' => $admin,
        ], 201);
    }

    /**
     * Display the specified admin.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $admin = Admin::with('branch')->find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $admin,
        ], 200);
    }

    /**
     * Update the specified admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found',
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('admins')->ignore($id)],
            'password' => 'sometimes|nullable|string|min:6',
            'admin_role' => ['sometimes', 'required', Rule::in(['super_admin', 'manager', 'trainee'])],
            'branch_id' => 'nullable|exists:branches,id',
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $admin->update([
            'name' => $request->name ?? $admin->name,
            'email' => $request->email ?? $admin->email,
            'admin_role' => $request->admin_role ?? $admin->admin_role,
            'branch_id' => $request->branch_id,
            'status' => $request->status ?? $admin->status,
        ]);

        // Update password only if provided
        if ($request->filled('password')) {
            $admin->update(['password' => Hash::make($request->password)]);
        }

        $admin->load('branch');

        return response()->json([
            'success' => true,
            'message' => 'Admin updated successfully',
            'data' => $admin,
        ], 200);
    }

    /**
     * Remove the specified admin.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found',
            ], 404);
        }

        $admin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin deleted successfully',
        ], 200);
    }
}
