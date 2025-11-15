<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Exports\CallLogsExport;
use Illuminate\Http\Request;
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
                } else {
                    $query->byUser($request->user_id)
                          ->whereHas('user', function ($q) use ($user) {
                              $q->where('branch_id', $user->branch_id);
                          });
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

        // Add recordings count
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

        $query = CallLog::query();
        $user = auth()->user();

        // Apply role-based filtering
        if ($user instanceof \App\Models\Admin) {
            if ($user->admin_role !== 'super_admin') {
                if ($user->branch_id) {
                    $query->whereHas('user', function ($q) use ($user) {
                        $q->where('branch_id', $user->branch_id);
                    });
                } else {
                    $query->whereRaw('1 = 0');
                }
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

        // Get total counts by call type
        $totalCalls = $query->count();
        $incoming = (clone $query)->where('call_type', 'incoming')->count();
        $outgoing = (clone $query)->where('call_type', 'outgoing')->count();
        $missed = (clone $query)->where('call_type', 'missed')->count();
        $rejected = (clone $query)->where('call_type', 'rejected')->count();

        // Get total duration
        $totalDuration = (clone $query)->sum('call_duration');

        // Get average duration
        $avgDuration = $totalCalls > 0 ? round($totalDuration / $totalCalls, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'total_calls' => $totalCalls,
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'missed' => $missed,
                'rejected' => $rejected,
                'total_duration' => $totalDuration,
                'average_duration' => $avgDuration,
            ],
        ], 200);
    }
}
