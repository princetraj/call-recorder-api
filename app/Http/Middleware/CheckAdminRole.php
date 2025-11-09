<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  ...$roles  (e.g., 'super_admin', 'manager', 'trainee')
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Check if user is authenticated and is an admin
        if (!$request->user('admin') || !$request->user('admin') instanceof \App\Models\Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $admin = $request->user('admin');

        // Check if admin is active
        if ($admin->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.'
            ], 403);
        }

        // If specific roles are required, check them
        if (!empty($roles) && !in_array($admin->admin_role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions for this action.'
            ], 403);
        }

        return $next($request);
    }
}
