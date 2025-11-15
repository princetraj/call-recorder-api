<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Device;
use App\Models\UserLoginActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Handle user login.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'password' => 'required|string|min:6',
        ]);

        $user = User::with('branch')->where('id', $request->user_id)->first();

        // Log failed login attempt if user not found or password incorrect
        if (!$user || !Hash::check($request->password, $user->password)) {
            UserLoginActivity::create([
                'user_id' => $user ? $user->id : null,
                'user_type' => 'user',
                'email' => $user ? $user->email : null,
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
        if ($user->status !== 'active') {
            UserLoginActivity::create([
                'user_id' => $user->id,
                'user_type' => 'user',
                'email' => $user->email,
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

        $token = $user->createToken('api-token')->plainTextToken;

        // Log successful login attempt
        UserLoginActivity::create([
            'user_id' => $user->id,
            'user_type' => 'user',
            'email' => $user->email,
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
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'status' => $user->status,
                    'branch_id' => $user->branch_id,
                    'branch' => $user->branch,
                ],
                'token' => $token,
            ],
        ], 200);
    }

    /**
     * Handle user logout.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        // Update the most recent login activity with logout time
        $loginActivity = UserLoginActivity::where('user_id', $user->id)
            ->where('user_type', 'user')
            ->where('status', 'success')
            ->whereNull('logout_at')
            ->latest('login_at')
            ->first();

        if ($loginActivity) {
            $loginActivity->update(['logout_at' => now()]);
        }

        // Delete the device if device_id is provided
        if ($request->has('device_id')) {
            Device::where('device_id', $request->device_id)
                ->where('user_id', $user->id)
                ->delete();
        }

        // Delete the current access token
        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }
}
