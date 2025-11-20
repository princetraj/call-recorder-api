<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceActivity;
use Illuminate\Http\Request;

class DeviceActivityController extends Controller
{
    /**
     * Get all device activities with filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = DeviceActivity::with(['device', 'user', 'admin'])
            ->orderBy('performed_at', 'desc');

        // Role-based filtering
        $admin = auth()->user();
        if ($admin instanceof \App\Models\Admin) {
            if ($admin->admin_role === 'manager') {
                // Manager sees only device activities from their branch
                if ($admin->branch_id) {
                    // Use JOIN for better performance
                    $query->join('users', 'device_activities.user_id', '=', 'users.id')
                          ->where('users.branch_id', $admin->branch_id)
                          ->select('device_activities.*');
                } else {
                    // If manager has no branch, show no results
                    $query->whereRaw('1 = 0');
                }
            }
            // Super admin sees all device activities
        }

        // Filter by action type (logout or removal)
        if ($request->has('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        // Filter by specific user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by specific admin who performed the action
        if ($request->has('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('performed_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('performed_at', '<=', $request->end_date);
        }

        // Search by device name, model, or user name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('device_name', 'like', '%' . $search . '%')
                  ->orWhere('device_model', 'like', '%' . $search . '%')
                  ->orWhere('device_id_value', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('admin', function($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $perPage = $request->input('per_page', 15);
        $deviceActivities = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $deviceActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'action_type' => $activity->action_type,
                        'user_id' => $activity->user_id,
                        'user_name' => $activity->user ? $activity->user->name : null,
                        'user_email' => $activity->user ? $activity->user->email : null,
                        'admin_id' => $activity->admin_id,
                        'admin_name' => $activity->admin ? $activity->admin->name : null,
                        'device_name' => $activity->device_name,
                        'device_model' => $activity->device_model,
                        'device_id_value' => $activity->device_id_value,
                        'ip_address' => $activity->ip_address,
                        'user_agent' => $activity->user_agent,
                        'notes' => $activity->notes,
                        'performed_at' => $activity->performed_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $deviceActivities->currentPage(),
                    'per_page' => $deviceActivities->perPage(),
                    'total' => $deviceActivities->total(),
                    'last_page' => $deviceActivities->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Get device activity statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        $query = DeviceActivity::query();

        // Role-based filtering
        $admin = auth()->user();
        if ($admin instanceof \App\Models\Admin) {
            if ($admin->admin_role === 'manager') {
                // Manager sees only device activities from their branch
                if ($admin->branch_id) {
                    $query->join('users', 'device_activities.user_id', '=', 'users.id')
                          ->where('users.branch_id', $admin->branch_id)
                          ->select('device_activities.*');
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('performed_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('performed_at', '<=', $request->end_date);
        }

        // Get statistics in a single query
        $stats = $query->selectRaw('
            COUNT(*) as total_activities,
            SUM(CASE WHEN action_type = "logout" THEN 1 ELSE 0 END) as logout_count,
            SUM(CASE WHEN action_type = "removal" THEN 1 ELSE 0 END) as removal_count
        ')->first();

        $totalActivities = $stats->total_activities ?? 0;
        $logoutCount = $stats->logout_count ?? 0;
        $removalCount = $stats->removal_count ?? 0;

        // Get most recent activities
        $recentQuery = DeviceActivity::with(['device', 'user', 'admin'])
            ->orderBy('performed_at', 'desc')
            ->limit(10);

        // Apply same role-based filtering
        if ($admin instanceof \App\Models\Admin) {
            if ($admin->admin_role === 'manager') {
                if ($admin->branch_id) {
                    $recentQuery->join('users', 'device_activities.user_id', '=', 'users.id')
                                ->where('users.branch_id', $admin->branch_id)
                                ->select('device_activities.*');
                } else {
                    $recentQuery->whereRaw('1 = 0');
                }
            }
        }

        $recentActivities = $recentQuery->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action_type' => $activity->action_type,
                    'user_name' => $activity->user ? $activity->user->name : null,
                    'admin_name' => $activity->admin ? $activity->admin->name : null,
                    'device_model' => $activity->device_model,
                    'performed_at' => $activity->performed_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_activities' => $totalActivities,
                'logout_count' => $logoutCount,
                'removal_count' => $removalCount,
                'recent_activities' => $recentActivities,
            ],
        ], 200);
    }

    /**
     * Get device activities for a specific user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function userActivity(Request $request, $userId)
    {
        $activities = DeviceActivity::with(['device', 'admin'])
            ->where('user_id', $userId)
            ->orderBy('performed_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $activities,
        ], 200);
    }

    /**
     * Get device activities performed by a specific admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $adminId
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminActivity(Request $request, $adminId)
    {
        $activities = DeviceActivity::with(['device', 'user'])
            ->where('admin_id', $adminId)
            ->orderBy('performed_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $activities,
        ], 200);
    }
}
