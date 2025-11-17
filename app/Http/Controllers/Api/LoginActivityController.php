<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserLoginActivity;
use Illuminate\Http\Request;

class LoginActivityController extends Controller
{
    /**
     * Get all login activities with filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = UserLoginActivity::with(['user', 'admin'])
            ->orderBy('login_at', 'desc');

        // Role-based filtering
        $admin = auth()->user();
        if ($admin instanceof \App\Models\Admin) {
            if ($admin->admin_role === 'manager') {
                // Manager sees only app user login activities from their branch
                $query->where('user_type', 'user');

                if ($admin->branch_id) {
                    $query->whereHas('user', function ($q) use ($admin) {
                        $q->where('branch_id', $admin->branch_id);
                    });
                } else {
                    // If manager has no branch, show no results
                    $query->whereRaw('1 = 0');
                }
            }
            // Super admin sees all login activities (no additional filtering)
        }

        // Filter by user type (user or admin)
        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by status (success or failed)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by specific user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by specific admin
        if ($request->has('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('login_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('login_at', '<=', $request->end_date);
        }

        // Search by email, user_id, or user name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', '%' . $search . '%')
                  ->orWhere('user_id', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('admin', function($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $perPage = $request->input('per_page', 15);
        $loginActivities = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $loginActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'user_type' => $activity->user_type,
                        'user_id' => $activity->user_id,
                        'email' => $activity->email,
                        'user_name' => $activity->user ? $activity->user->name : ($activity->admin ? $activity->admin->name : null),
                        'ip_address' => $activity->ip_address,
                        'user_agent' => $activity->user_agent,
                        'device_name' => $activity->device_name,
                        'device_id' => $activity->device_id,
                        'status' => $activity->status,
                        'failure_reason' => $activity->failure_reason,
                        'login_at' => $activity->login_at,
                        'logout_at' => $activity->logout_at,
                        'session_duration' => $activity->logout_at
                            ? $activity->login_at->diffInMinutes($activity->logout_at)
                            : null,
                    ];
                }),
                'pagination' => [
                    'current_page' => $loginActivities->currentPage(),
                    'per_page' => $loginActivities->perPage(),
                    'total' => $loginActivities->total(),
                    'last_page' => $loginActivities->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Get login activity statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        $query = UserLoginActivity::query();

        // Role-based filtering
        $admin = auth()->user();
        if ($admin instanceof \App\Models\Admin) {
            if ($admin->admin_role === 'manager') {
                // Manager sees only app user login statistics from their branch
                $query->where('user_type', 'user');

                if ($admin->branch_id) {
                    $query->whereHas('user', function ($q) use ($admin) {
                        $q->where('branch_id', $admin->branch_id);
                    });
                } else {
                    // If manager has no branch, show no results
                    $query->whereRaw('1 = 0');
                }
            }
            // Super admin sees all statistics (no additional filtering)
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('login_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('login_at', '<=', $request->end_date);
        }

        $totalLogins = $query->count();
        $successfulLogins = (clone $query)->where('status', 'success')->count();
        $failedLogins = (clone $query)->where('status', 'failed')->count();
        $userLogins = (clone $query)->where('user_type', 'user')->count();
        $adminLogins = (clone $query)->where('user_type', 'admin')->count();

        // Get most recent activities with same filtering
        $recentQuery = UserLoginActivity::with(['user', 'admin'])
            ->orderBy('login_at', 'desc')
            ->limit(10);

        // Apply same role-based filtering for recent activities
        if ($admin instanceof \App\Models\Admin) {
            if ($admin->admin_role === 'manager') {
                $recentQuery->where('user_type', 'user');

                if ($admin->branch_id) {
                    $recentQuery->whereHas('user', function ($q) use ($admin) {
                        $q->where('branch_id', $admin->branch_id);
                    });
                } else {
                    $recentQuery->whereRaw('1 = 0');
                }
            }
        }

        $recentActivities = $recentQuery->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'user_type' => $activity->user_type,
                    'user_id' => $activity->user_id,
                    'email' => $activity->email,
                    'user_name' => $activity->user ? $activity->user->name : ($activity->admin ? $activity->admin->name : null),
                    'status' => $activity->status,
                    'login_at' => $activity->login_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_logins' => $totalLogins,
                'successful_logins' => $successfulLogins,
                'failed_logins' => $failedLogins,
                'user_logins' => $userLogins,
                'admin_logins' => $adminLogins,
                'success_rate' => $totalLogins > 0 ? round(($successfulLogins / $totalLogins) * 100, 2) : 0,
                'recent_activities' => $recentActivities,
            ],
        ], 200);
    }

    /**
     * Get login activity for a specific user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function userActivity(Request $request, $userId)
    {
        $activities = UserLoginActivity::where('user_id', $userId)
            ->orderBy('login_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $activities,
        ], 200);
    }

    /**
     * Get login activity for a specific admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $adminId
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminActivity(Request $request, $adminId)
    {
        $activities = UserLoginActivity::where('admin_id', $adminId)
            ->orderBy('login_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $activities,
        ], 200);
    }
}
