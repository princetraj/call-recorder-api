<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    /**
     * Store new call log(s).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Check if bulk insert or single insert
        if ($request->has('call_logs')) {
            // Bulk insert validation
            $request->validate([
                'call_logs' => 'required|array|min:1',
                'call_logs.*.caller_name' => 'nullable|string|max:255',
                'call_logs.*.caller_number' => 'required|string|max:50',
                'call_logs.*.call_type' => 'required|in:incoming,outgoing,missed,rejected',
                'call_logs.*.call_duration' => 'required|integer|min:0',
                'call_logs.*.call_timestamp' => 'required|date_format:Y-m-d H:i:s',
                'call_logs.*.notes' => 'nullable|string',
            ]);

            $createdLogs = [];
            foreach ($request->call_logs as $logData) {
                $logData['user_id'] = auth()->id();
                $createdLogs[] = CallLog::create($logData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Call log(s) created successfully',
                'data' => [
                    'call_logs' => $createdLogs,
                ],
            ], 201);
        } else {
            // Single insert validation
            $request->validate([
                'caller_name' => 'nullable|string|max:255',
                'caller_number' => 'required|string|max:50',
                'call_type' => 'required|in:incoming,outgoing,missed,rejected',
                'call_duration' => 'required|integer|min:0',
                'call_timestamp' => 'required|date_format:Y-m-d H:i:s',
                'notes' => 'nullable|string',
            ]);

            $callLog = CallLog::create([
                'user_id' => auth()->id(),
                'caller_name' => $request->caller_name,
                'caller_number' => $request->caller_number,
                'call_type' => $request->call_type,
                'call_duration' => $request->call_duration,
                'call_timestamp' => $request->call_timestamp,
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call log(s) created successfully',
                'data' => [
                    'call_logs' => [$callLog],
                ],
            ], 201);
        }
    }

    /**
     * Display all call logs with pagination and filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = CallLog::query();

        // Check if the authenticated user is an Admin or regular User
        $user = auth()->user();

        if ($user instanceof \App\Models\Admin) {
            // Admin user - show call logs with user and branch data
            $query->with(['user', 'user.branch']);

            // Role-based filtering
            if ($user->admin_role === 'super_admin') {
                // Super admin sees all call logs from all branches
                // No additional filtering needed
            } else {
                // Manager and Trainee see only call logs from their branch
                if ($user->branch_id) {
                    $query->whereHas('user', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } else {
                    // If admin has no branch assigned, show no results
                    $query->whereRaw('1 = 0');
                }
            }
        } else {
            // Regular user - show only their call logs
            $query->where('user_id', auth()->id());
        }

        // Apply filters
        // Filter by call type
        if ($request->has('call_type')) {
            $query->ofType($request->call_type);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        // Filter by specific date
        if ($request->has('date')) {
            $query->byDate($request->date);
        }

        // Filter by time range
        if ($request->has('time_from') && $request->has('time_to')) {
            $query->timeRange($request->time_from, $request->time_to);
        }

        // Filter by duration range (in seconds)
        if ($request->has('duration_min') && $request->has('duration_max')) {
            $query->durationRange($request->duration_min, $request->duration_max);
        }

        // Filter by agent/user - with role-based access control
        if ($request->has('user_id')) {
            if ($user instanceof \App\Models\Admin) {
                // For admins, check if they have permission to filter by this user
                if ($user->admin_role === 'super_admin') {
                    // Super admin can filter by any user
                    $query->byUser($request->user_id);
                } else {
                    // Manager/Trainee can only filter by users in their branch
                    $query->byUser($request->user_id)
                          ->whereHas('user', function ($q) use ($user) {
                              $q->where('branch_id', $user->branch_id);
                          });
                }
            }
        }

        // Filter by branch - only for super admin
        if ($request->has('branch_id')) {
            if ($user instanceof \App\Models\Admin && $user->admin_role === 'super_admin') {
                $query->byBranch($request->branch_id);
            }
        }

        // Filter by caller number
        if ($request->has('number')) {
            $query->byNumber($request->number);
        }

        // General search by caller name or number
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Add recordings count
        $query->withCount('recordings');

        // Order by most recent
        $query->orderBy('call_timestamp', 'desc');

        // Paginate
        $perPage = $request->get('per_page', 20);
        $perPage = min($perPage, 100); // Max 100 per page

        $callLogs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Call logs retrieved successfully',
            'data' => [
                'call_logs' => $callLogs->items(),
                'pagination' => [
                    'current_page' => $callLogs->currentPage(),
                    'per_page' => $callLogs->perPage(),
                    'total' => $callLogs->total(),
                    'last_page' => $callLogs->lastPage(),
                    'from' => $callLogs->firstItem(),
                    'to' => $callLogs->lastItem(),
                ],
            ],
        ], 200);
    }

    /**
     * Display a specific call log with recordings.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $query = CallLog::query();

        // Check if the authenticated user is an Admin or regular User
        $user = auth()->user();

        if ($user instanceof \App\Models\Admin) {
            // Admin user - can view call logs with user and branch data
            $query->with(['user', 'user.branch']);

            // Role-based filtering
            if ($user->admin_role === 'super_admin') {
                // Super admin can view any call log
                // No additional filtering needed
            } else {
                // Manager and Trainee can only view call logs from their branch
                if ($user->branch_id) {
                    $query->whereHas('user', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } else {
                    // If admin has no branch assigned, show no results
                    $query->whereRaw('1 = 0');
                }
            }
        } else {
            // Regular user - show only their call log
            $query->where('user_id', auth()->id());
        }

        $callLog = $query->where('id', $id)->with('recordings')->first();

        if (!$callLog) {
            return response()->json([
                'success' => false,
                'message' => 'Call log not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Call log retrieved successfully',
            'data' => [
                'call_log' => $callLog,
            ],
        ], 200);
    }
}
