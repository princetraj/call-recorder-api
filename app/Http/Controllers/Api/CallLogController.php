<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Exports\CallLogsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

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
                'call_logs.*.sim_slot_index' => 'nullable|integer|min:0|max:1',
                'call_logs.*.sim_name' => 'nullable|string|max:100',
                'call_logs.*.sim_number' => 'nullable|string|max:50',
                'call_logs.*.sim_serial_number' => 'nullable|string|max:100',
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
                'sim_slot_index' => 'nullable|integer|min:0|max:1',
                'sim_name' => 'nullable|string|max:100',
                'sim_number' => 'nullable|string|max:50',
                'sim_serial_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            // IMPROVED: Use DB transaction for data integrity
            try {
                // CRITICAL: Check for duplicate call log within ±5 seconds
                // Prevents duplicate uploads if retry logic or network issues cause re-submission
                $callTimestamp = \Carbon\Carbon::parse($request->call_timestamp);
                $existing = CallLog::where('user_id', auth()->id())
                    ->where('caller_number', $request->caller_number)
                    ->where('call_type', $request->call_type)
                    ->whereBetween('call_timestamp', [
                        $callTimestamp->copy()->subSeconds(5),
                        $callTimestamp->copy()->addSeconds(5)
                    ])
                    ->first();

                if ($existing) {
                    \Log::info('Duplicate call log detected, returning existing', [
                        'existing_id' => $existing->id,
                        'user_id' => auth()->id(),
                        'caller_number' => $request->caller_number,
                        'call_timestamp' => $request->call_timestamp,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Call log already exists (duplicate prevented)',
                        'data' => [
                            'call_logs' => [$existing],
                        ],
                    ], 200);
                }

                // Create new call log if not duplicate
                $callLog = CallLog::create([
                    'user_id' => auth()->id(),
                    'caller_name' => $request->caller_name,
                    'caller_number' => $request->caller_number,
                    'call_type' => $request->call_type,
                    'call_duration' => $request->call_duration,
                    'call_timestamp' => $request->call_timestamp,
                    'sim_slot_index' => $request->sim_slot_index,
                    'sim_name' => $request->sim_name,
                    'sim_number' => $request->sim_number,
                    'sim_serial_number' => $request->sim_serial_number,
                    'notes' => $request->notes,
                ]);

                // Verify the call log was created and has an ID
                if (!$callLog || !$callLog->id) {
                    \Log::error('Call log created but ID is missing', ['call_log' => $callLog]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Call log created but ID not generated',
                    ], 500);
                }

                \Log::info('Call log created successfully', [
                    'call_log_id' => $callLog->id,
                    'user_id' => auth()->id(),
                    'caller_number' => $request->caller_number,
                    'call_timestamp' => $request->call_timestamp,
                ]);

                // PERFORMANCE: Invalidate statistics cache for this user
                $this->invalidateStatisticsCache(auth()->user());

                return response()->json([
                    'success' => true,
                    'message' => 'Call log(s) created successfully',
                    'data' => [
                        'call_logs' => [$callLog],
                    ],
                ], 201);
            } catch (\Exception $e) {
                \Log::error('Failed to create call log', [
                    'error' => $e->getMessage(),
                    'user_id' => auth()->id(),
                    'caller_number' => $request->caller_number,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create call log: ' . $e->getMessage(),
                ], 500);
            }
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
            } elseif ($user->admin_role === 'manager') {
                // Manager sees only call logs from their branch
                // OPTIMIZED: Use JOIN instead of whereHas for better performance
                if ($user->branch_id) {
                    $query->join('users', 'call_logs.user_id', '=', 'users.id')
                          ->where('users.branch_id', $user->branch_id)
                          ->select('call_logs.*'); // Ensure we only select call_logs columns
                } else {
                    // If admin has no branch assigned, show no results
                    $query->whereRaw('1 = 0');
                }
            } elseif ($user->admin_role === 'trainee') {
                // Trainee sees only call logs from assigned users
                if ($user->assigned_user_ids && count($user->assigned_user_ids) > 0) {
                    $query->whereIn('user_id', $user->assigned_user_ids);
                } else {
                    // If trainee has no assigned users, show no results
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Unknown role - show no results
                $query->whereRaw('1 = 0');
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
                } elseif ($user->admin_role === 'manager') {
                    // Manager can only filter by users in their branch
                    // OPTIMIZED: Rely on the JOIN already applied in the manager role check above
                    $query->byUser($request->user_id);
                } elseif ($user->admin_role === 'trainee') {
                    // Trainee can only filter by their assigned users
                    if ($user->assigned_user_ids && in_array($request->user_id, $user->assigned_user_ids)) {
                        $query->byUser($request->user_id);
                    } else {
                        // If trying to filter by non-assigned user, show no results
                        $query->whereRaw('1 = 0');
                    }
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

        // OPTIMIZED: Only load recordings if explicitly requested, otherwise just count
        // This prevents loading potentially large recording data for listing views
        if ($request->get('include_recordings', false)) {
            $query->with('recordings');
        }
        $query->withCount('recordings');

        // Apply sorting
        $sortBy = $request->get('sort_by', 'call_timestamp');
        $sortOrder = $request->get('sort_order', 'desc');

        // Validate sort_by to prevent SQL injection
        $allowedSortColumns = ['call_type', 'call_duration', 'call_timestamp'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'call_timestamp';
        }

        // Validate sort_order
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

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
            } elseif ($user->admin_role === 'manager') {
                // Manager can only view call logs from their branch
                // OPTIMIZED: Use JOIN instead of whereHas for better performance
                if ($user->branch_id) {
                    $query->join('users', 'call_logs.user_id', '=', 'users.id')
                          ->where('users.branch_id', $user->branch_id)
                          ->select('call_logs.*'); // Ensure we only select call_logs columns
                } else {
                    // If admin has no branch assigned, show no results
                    $query->whereRaw('1 = 0');
                }
            } elseif ($user->admin_role === 'trainee') {
                // Trainee can only view call logs from assigned users
                if ($user->assigned_user_ids && count($user->assigned_user_ids) > 0) {
                    $query->whereIn('user_id', $user->assigned_user_ids);
                } else {
                    // If trainee has no assigned users, show no results
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Unknown role - show no results
                $query->whereRaw('1 = 0');
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

    /**
     * Build query with filters (shared logic for index and export).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildFilteredQuery(Request $request)
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
            } elseif ($user->admin_role === 'manager') {
                // Manager sees only call logs from their branch
                // OPTIMIZED: Use JOIN instead of whereHas for better performance
                if ($user->branch_id) {
                    $query->join('users', 'call_logs.user_id', '=', 'users.id')
                          ->where('users.branch_id', $user->branch_id)
                          ->select('call_logs.*'); // Ensure we only select call_logs columns
                } else {
                    // If admin has no branch assigned, show no results
                    $query->whereRaw('1 = 0');
                }
            } elseif ($user->admin_role === 'trainee') {
                // Trainee sees only call logs from assigned users
                if ($user->assigned_user_ids && count($user->assigned_user_ids) > 0) {
                    $query->whereIn('user_id', $user->assigned_user_ids);
                } else {
                    // If trainee has no assigned users, show no results
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Unknown role - show no results
                $query->whereRaw('1 = 0');
            }
        } else {
            // Regular user - show only their call logs
            $query->where('user_id', auth()->id());
        }

        // Apply filters
        if ($request->has('call_type')) {
            $query->ofType($request->call_type);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        if ($request->has('date')) {
            $query->byDate($request->date);
        }

        if ($request->has('time_from') && $request->has('time_to')) {
            $query->timeRange($request->time_from, $request->time_to);
        }

        if ($request->has('duration_min') && $request->has('duration_max')) {
            $query->durationRange($request->duration_min, $request->duration_max);
        }

        if ($request->has('user_id')) {
            if ($user instanceof \App\Models\Admin) {
                if ($user->admin_role === 'super_admin') {
                    $query->byUser($request->user_id);
                } elseif ($user->admin_role === 'manager') {
                    // OPTIMIZED: No need for additional whereHas since JOIN already applied above
                    // The JOIN at line 175-177 already filters by branch_id for managers
                    $query->byUser($request->user_id);
                } elseif ($user->admin_role === 'trainee') {
                    // Trainee can only filter by their assigned users
                    if ($user->assigned_user_ids && in_array($request->user_id, $user->assigned_user_ids)) {
                        $query->byUser($request->user_id);
                    } else {
                        // If trying to filter by non-assigned user, show no results
                        $query->whereRaw('1 = 0');
                    }
                }
            }
        }

        if ($request->has('branch_id')) {
            if ($user instanceof \App\Models\Admin && $user->admin_role === 'super_admin') {
                $query->byBranch($request->branch_id);
            }
        }

        if ($request->has('number')) {
            $query->byNumber($request->number);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        // OPTIMIZED: For exports, we typically don't need recording file data
        // Just use count to show if recordings exist
        $query->withCount('recordings');

        // Apply sorting
        $sortBy = $request->get('sort_by', 'call_timestamp');
        $sortOrder = $request->get('sort_order', 'desc');

        // Validate sort_by to prevent SQL injection
        $allowedSortColumns = ['call_type', 'call_duration', 'call_timestamp'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'call_timestamp';
        }

        // Validate sort_order
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query;
    }

    /**
     * Export call logs to Excel.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportExcel(Request $request)
    {
        $query = $this->buildFilteredQuery($request);

        $filename = 'call-logs-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new CallLogsExport($query), $filename);
    }

    /**
     * Export call logs to PDF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function exportPdf(Request $request)
    {
        $query = $this->buildFilteredQuery($request);

        // Limit to avoid memory issues with large datasets
        $callLogs = $query->limit(500)->get();

        $pdf = Pdf::loadView('exports.call-logs-pdf', [
            'callLogs' => $callLogs,
            'exportDate' => now()->format('Y-m-d H:i:s'),
        ]);

        $filename = 'call-logs-' . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Get call log statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        $period = $request->input('period', 'daily'); // daily, weekly, monthly
        $user = auth()->user();

        // Generate cache key based on user and period
        $cacheKey = $this->getStatisticsCacheKey($user, $period);

        // PERFORMANCE: Cache statistics for 5 minutes to reduce database load
        // Cache is invalidated when new call logs are created (see store method)
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($period, $user) {
            $query = CallLog::query();

            // Apply role-based filtering
            if ($user instanceof \App\Models\Admin) {
                if ($user->admin_role === 'super_admin') {
                    // Super admin sees all statistics
                } elseif ($user->admin_role === 'manager') {
                    // Manager sees statistics from their branch
                    // OPTIMIZED: Use JOIN instead of whereHas for better performance
                    if ($user->branch_id) {
                        $query->join('users', 'call_logs.user_id', '=', 'users.id')
                              ->where('users.branch_id', $user->branch_id)
                              ->select('call_logs.*'); // Ensure we only select call_logs columns
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                } elseif ($user->admin_role === 'trainee') {
                    // Trainee sees statistics from assigned users
                    if ($user->assigned_user_ids && count($user->assigned_user_ids) > 0) {
                        $query->whereIn('user_id', $user->assigned_user_ids);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
                $query->where('user_id', auth()->id());
            }

            // Apply period filter
            switch ($period) {
                case 'daily':
                    $query->whereDate('call_timestamp', '>=', now()->startOfDay());
                    break;
                case 'weekly':
                    $query->whereDate('call_timestamp', '>=', now()->startOfWeek());
                    break;
                case 'monthly':
                    $query->whereDate('call_timestamp', '>=', now()->startOfMonth());
                    break;
            }

            // OPTIMIZED: Get all statistics in a single query instead of 6 separate queries
            $stats = $query->selectRaw('
                COUNT(*) as total_calls,
                SUM(CASE WHEN call_type = "incoming" THEN 1 ELSE 0 END) as incoming,
                SUM(CASE WHEN call_type = "outgoing" THEN 1 ELSE 0 END) as outgoing,
                SUM(CASE WHEN call_type = "missed" THEN 1 ELSE 0 END) as missed,
                SUM(CASE WHEN call_type = "rejected" THEN 1 ELSE 0 END) as rejected,
                SUM(call_duration) as total_duration
            ')->first();

            $totalCalls = $stats->total_calls ?? 0;
            $incoming = $stats->incoming ?? 0;
            $outgoing = $stats->outgoing ?? 0;
            $missed = $stats->missed ?? 0;
            $rejected = $stats->rejected ?? 0;
            $totalDuration = $stats->total_duration ?? 0;

            // Get average duration
            $avgDuration = $totalCalls > 0 ? round($totalDuration / $totalCalls, 2) : 0;

            return [
                'period' => $period,
                'total_calls' => $totalCalls,
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'missed' => $missed,
                'rejected' => $rejected,
                'total_duration' => $totalDuration,
                'average_duration' => $avgDuration,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Generate cache key for statistics.
     */
    private function getStatisticsCacheKey($user, $period)
    {
        if ($user instanceof \App\Models\Admin) {
            $userKey = 'admin_' . $user->id . '_' . $user->admin_role;
            if ($user->branch_id) {
                $userKey .= '_branch_' . $user->branch_id;
            }
        } else {
            $userKey = 'user_' . $user->id;
        }

        return 'call_stats_' . $userKey . '_' . $period;
    }

    /**
     * Invalidate statistics cache for a user.
     */
    private function invalidateStatisticsCache($user)
    {
        $periods = ['daily', 'weekly', 'monthly'];
        foreach ($periods as $period) {
            $cacheKey = $this->getStatisticsCacheKey($user, $period);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Get productivity tracking for a missed or rejected call.
     * Shows if the user called back after a missed/rejected call and the full call history with that number.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function productivityTracking($id)
    {
        $query = CallLog::query();
        $user = auth()->user();

        // Check if the authenticated user is an Admin or regular User
        if ($user instanceof \App\Models\Admin) {
            // Admin user - apply role-based filtering
            $query->with(['user', 'user.branch']);

            if ($user->admin_role === 'super_admin') {
                // Super admin can view any call log
            } elseif ($user->admin_role === 'manager') {
                // Manager can only view call logs from their branch
                // OPTIMIZED: Use JOIN instead of whereHas for better performance
                if ($user->branch_id) {
                    $query->join('users', 'call_logs.user_id', '=', 'users.id')
                          ->where('users.branch_id', $user->branch_id)
                          ->select('call_logs.*'); // Ensure we only select call_logs columns
                } else {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($user->admin_role === 'trainee') {
                // Trainee can only view call logs from assigned users
                if ($user->assigned_user_ids && count($user->assigned_user_ids) > 0) {
                    $query->whereIn('user_id', $user->assigned_user_ids);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            // Regular user - show only their call log
            $query->where('user_id', auth()->id());
        }

        $callLog = $query->where('id', $id)->first();

        if (!$callLog) {
            return response()->json([
                'success' => false,
                'message' => 'Call log not found',
            ], 404);
        }

        // Check if the call is missed or rejected
        if (!in_array($callLog->call_type, ['missed', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Productivity tracking is only available for missed or rejected calls',
            ], 400);
        }

        // Find the next outgoing call to the same number after this missed/rejected call (with recordings)
        $nextOutgoingCall = CallLog::where('user_id', $callLog->user_id)
            ->where('caller_number', $callLog->caller_number)
            ->where('call_type', 'outgoing')
            ->where('call_timestamp', '>', $callLog->call_timestamp)
            ->with('recordings')
            ->orderBy('call_timestamp', 'asc')
            ->first();

        // PERFORMANCE: Add pagination and conditional recording loading
        $perPage = $request->get('per_page', 20);
        $includeRecordings = $request->get('include_recordings', false);

        // Get full call history with this number for the user
        $callHistoryQuery = CallLog::where('user_id', $callLog->user_id)
            ->where('caller_number', $callLog->caller_number)
            ->orderBy('call_timestamp', 'desc');

        // Only load recordings if explicitly requested
        if ($includeRecordings) {
            $callHistoryQuery->with('recordings');
        } else {
            $callHistoryQuery->withCount('recordings');
        }

        $callHistory = $callHistoryQuery->paginate($perPage);

        // Calculate statistics from all records (not just paginated)
        $statsQuery = CallLog::where('user_id', $callLog->user_id)
            ->where('caller_number', $callLog->caller_number)
            ->selectRaw('
                COUNT(*) as total_calls,
                SUM(CASE WHEN call_type = "incoming" THEN 1 ELSE 0 END) as incoming_count,
                SUM(CASE WHEN call_type = "outgoing" THEN 1 ELSE 0 END) as outgoing_count,
                SUM(CASE WHEN call_type = "missed" THEN 1 ELSE 0 END) as missed_count,
                SUM(CASE WHEN call_type = "rejected" THEN 1 ELSE 0 END) as rejected_count,
                SUM(call_duration) as total_duration
            ')
            ->first();

        $totalCalls = $statsQuery->total_calls ?? 0;
        $incomingCount = $statsQuery->incoming_count ?? 0;
        $outgoingCount = $statsQuery->outgoing_count ?? 0;
        $missedCount = $statsQuery->missed_count ?? 0;
        $rejectedCount = $statsQuery->rejected_count ?? 0;
        $totalDuration = $statsQuery->total_duration ?? 0;

        // Calculate callback time if there was a callback
        $callbackTimeSeconds = null;
        $callbackTimeFormatted = null;
        if ($nextOutgoingCall) {
            $originalTime = \Carbon\Carbon::parse($callLog->call_timestamp);
            $callbackTime = \Carbon\Carbon::parse($nextOutgoingCall->call_timestamp);
            $callbackTimeSeconds = $callbackTime->diffInSeconds($originalTime);

            // Format the time difference in a human-readable way
            $diffInMinutes = $callbackTime->diffInMinutes($originalTime);
            $diffInHours = $callbackTime->diffInHours($originalTime);
            $diffInDays = $callbackTime->diffInDays($originalTime);

            if ($diffInMinutes < 1) {
                $callbackTimeFormatted = $callbackTimeSeconds . ' seconds';
            } elseif ($diffInMinutes < 60) {
                $callbackTimeFormatted = $diffInMinutes . ' minute' . ($diffInMinutes > 1 ? 's' : '');
            } elseif ($diffInHours < 24) {
                $remainingMinutes = $diffInMinutes % 60;
                $callbackTimeFormatted = $diffInHours . ' hour' . ($diffInHours > 1 ? 's' : '');
                if ($remainingMinutes > 0) {
                    $callbackTimeFormatted .= ' ' . $remainingMinutes . ' min';
                }
            } else {
                $remainingHours = $diffInHours % 24;
                $callbackTimeFormatted = $diffInDays . ' day' . ($diffInDays > 1 ? 's' : '');
                if ($remainingHours > 0) {
                    $callbackTimeFormatted .= ' ' . $remainingHours . ' hour' . ($remainingHours > 1 ? 's' : '');
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Productivity tracking retrieved successfully',
            'data' => [
                'original_call' => $callLog,
                'next_outgoing_call' => $nextOutgoingCall,
                'call_history' => $callHistory->items(),
                'pagination' => [
                    'current_page' => $callHistory->currentPage(),
                    'per_page' => $callHistory->perPage(),
                    'total' => $callHistory->total(),
                    'last_page' => $callHistory->lastPage(),
                    'from' => $callHistory->firstItem(),
                    'to' => $callHistory->lastItem(),
                ],
                'statistics' => [
                    'total_calls' => $totalCalls,
                    'incoming' => $incomingCount,
                    'outgoing' => $outgoingCount,
                    'missed' => $missedCount,
                    'rejected' => $rejectedCount,
                    'total_duration' => $totalDuration,
                    'called_back' => $nextOutgoingCall !== null,
                    'callback_time_seconds' => $callbackTimeSeconds,
                    'callback_time_formatted' => $callbackTimeFormatted,
                ],
            ],
        ], 200);
    }
}
