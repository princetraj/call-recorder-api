<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\UserLoginActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    /**
     * Handle admin login.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $admin = Admin::with('branch')->where('email', $request->email)->first();

        // Log failed login attempt if admin not found or password incorrect
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            UserLoginActivity::create([
                'admin_id' => $admin ? $admin->id : null,
                'user_type' => 'admin',
                'email' => $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_name' => $request->input('device_name'),
                'device_id' => $request->input('device_id'),
                'status' => 'failed',
                'failure_reason' => 'Invalid credentials',
                'login_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Log failed login attempt if account is inactive
        if ($admin->status !== 'active') {
            UserLoginActivity::create([
                'admin_id' => $admin->id,
                'user_type' => 'admin',
                'email' => $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_name' => $request->input('device_name'),
                'device_id' => $request->input('device_id'),
                'status' => 'failed',
                'failure_reason' => 'Account is inactive',
                'login_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Account is inactive',
            ], 403);
        }

        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        // Log successful login attempt
        UserLoginActivity::create([
            'admin_id' => $admin->id,
            'user_type' => 'admin',
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_name' => $request->input('device_name'),
            'device_id' => $request->input('device_id'),
            'status' => 'success',
            'login_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'admin_role' => $admin->admin_role,
                    'status' => $admin->status,
                    'branch_id' => $admin->branch_id,
                    'branch' => $admin->branch,
                ],
                'token' => $token,
            ],
        ], 200);
    }

    /**
     * Handle admin logout.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $admin = $request->user('admin');

        // Update the most recent login activity with logout time
        $loginActivity = UserLoginActivity::where('admin_id', $admin->id)
            ->where('user_type', 'admin')
            ->where('status', 'success')
            ->whereNull('logout_at')
            ->latest('login_at')
            ->first();

        if ($loginActivity) {
            $loginActivity->update(['logout_at' => now()]);
        }

        $admin->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Get authenticated admin details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $admin = $request->user('admin')->load('branch');

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'admin_role' => $admin->admin_role,
                    'status' => $admin->status,
                    'branch_id' => $admin->branch_id,
                    'branch' => $admin->branch,
                ],
            ],
        ], 200);
    }
}
