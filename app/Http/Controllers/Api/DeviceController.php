<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:255',
            'device_model' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:100',
            'os_version' => 'nullable|string|max:50',
            'app_version' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $device = Device::updateOrCreate(
                [
                    'device_id' => $request->device_id,
                ],
                [
                    'user_id' => $request->user()->id,
                    'device_model' => $request->device_model,
                    'manufacturer' => $request->manufacturer,
                    'os_version' => $request->os_version,
                    'app_version' => $request->app_version,
                    'last_updated_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Device registered successfully',
                'data' => $device
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register device',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:255',
            'connection_type' => 'nullable|string|in:wifi,mobile,none',
            'battery_percentage' => 'nullable|integer|min:0|max:100',
            'signal_strength' => 'nullable|integer|min:0|max:4',
            'is_charging' => 'nullable|boolean',
            'app_running_status' => 'nullable|string|in:active,background,stopped',
            'current_call_status' => 'nullable|string|in:idle,in_call',
            'current_call_number' => 'nullable|string|max:20',
            'permissions' => 'nullable|array',
            'permissions.read_call_log' => 'nullable|boolean',
            'permissions.read_phone_state' => 'nullable|boolean',
            'permissions.read_contacts' => 'nullable|boolean',
            'permissions.read_external_storage' => 'nullable|boolean',
            'permissions.read_media_audio' => 'nullable|boolean',
            'permissions.post_notifications' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $device = Device::where('device_id', $request->device_id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$device) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not found. Please register the device first.'
                ], 404);
            }

            // Check if device should logout
            $shouldLogout = $device->should_logout;

            // Prepare update data
            $updateData = [
                'connection_type' => $request->connection_type ?? $device->connection_type,
                'battery_percentage' => $request->battery_percentage ?? $device->battery_percentage,
                'signal_strength' => $request->signal_strength ?? $device->signal_strength,
                'is_charging' => $request->is_charging ?? $device->is_charging,
                'app_running_status' => $request->app_running_status ?? $device->app_running_status,
                'current_call_status' => $request->current_call_status ?? $device->current_call_status,
                'current_call_number' => $request->current_call_number ?? $device->current_call_number,
                'call_started_at' => $request->current_call_status && $request->current_call_status !== 'idle' ? ($device->current_call_status === 'idle' ? now() : $device->call_started_at) : null,
                'last_updated_at' => now(),
                'should_logout' => false, // Clear the logout flag after sending it to device
            ];

            // Add permission data if provided
            if ($request->has('permissions')) {
                $permissions = $request->input('permissions');
                $updateData['perm_read_call_log'] = $permissions['read_call_log'] ?? $device->perm_read_call_log;
                $updateData['perm_read_phone_state'] = $permissions['read_phone_state'] ?? $device->perm_read_phone_state;
                $updateData['perm_read_contacts'] = $permissions['read_contacts'] ?? $device->perm_read_contacts;
                $updateData['perm_read_external_storage'] = $permissions['read_external_storage'] ?? $device->perm_read_external_storage;
                $updateData['perm_read_media_audio'] = $permissions['read_media_audio'] ?? $device->perm_read_media_audio;
                $updateData['perm_post_notifications'] = $permissions['post_notifications'] ?? $device->perm_post_notifications;
            }

            $device->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Device status updated successfully',
                'data' => $device->fresh(),
                'should_logout' => $shouldLogout
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update device status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = Device::with(['user:id,name,email,branch_id', 'user.branch:id,name'])
                ->orderBy('last_updated_at', 'desc');

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->whereHas('user', function($q) use ($request) {
                    $q->where('branch_id', $request->branch_id);
                });
            }

            // Filter by status (online/offline)
            if ($request->has('status')) {
                if ($request->status === 'online') {
                    $query->where('last_updated_at', '>=', now()->subMinutes(10));
                } elseif ($request->status === 'offline') {
                    $query->where(function($q) {
                        $q->where('last_updated_at', '<', now()->subMinutes(10))
                          ->orWhereNull('last_updated_at');
                    });
                }
            }

            // Filter by permission status
            if ($request->has('permission_status')) {
                if ($request->permission_status === 'all_granted') {
                    $query->where('perm_read_call_log', true)
                          ->where('perm_read_phone_state', true)
                          ->where('perm_read_contacts', true)
                          ->where('perm_read_external_storage', true)
                          ->where('perm_read_media_audio', true)
                          ->where('perm_post_notifications', true);
                } elseif ($request->permission_status === 'some_denied') {
                    $query->where(function($q) {
                        $q->where('perm_read_call_log', false)
                          ->orWhere('perm_read_phone_state', false)
                          ->orWhere('perm_read_contacts', false)
                          ->orWhere('perm_read_external_storage', false)
                          ->orWhere('perm_read_media_audio', false)
                          ->orWhere('perm_post_notifications', false);
                    });
                } elseif ($request->permission_status === 'all_denied') {
                    $query->where('perm_read_call_log', false)
                          ->where('perm_read_phone_state', false)
                          ->where('perm_read_contacts', false)
                          ->where('perm_read_external_storage', false)
                          ->where('perm_read_media_audio', false)
                          ->where('perm_post_notifications', false);
                }
            }

            $perPage = $request->input('per_page', 20);
            $devices = $query->paginate($perPage);

            $devices->getCollection()->transform(function ($device) {
                $userData = null;
                if ($device->user) {
                    $userData = [
                        'id' => $device->user->id,
                        'name' => $device->user->name,
                        'email' => $device->user->email,
                        'branch' => $device->user->branch ? [
                            'id' => $device->user->branch->id,
                            'name' => $device->user->branch->name,
                        ] : null,
                    ];
                }

                return [
                    'id' => $device->id,
                    'user' => $userData,
                    'device_id' => $device->device_id,
                    'device_model' => $device->device_model,
                    'manufacturer' => $device->manufacturer,
                    'os_version' => $device->os_version,
                    'app_version' => $device->app_version,
                    'permissions' => [
                        'read_call_log' => $device->perm_read_call_log,
                        'read_phone_state' => $device->perm_read_phone_state,
                        'read_contacts' => $device->perm_read_contacts,
                        'read_external_storage' => $device->perm_read_external_storage,
                        'read_media_audio' => $device->perm_read_media_audio,
                        'post_notifications' => $device->perm_post_notifications,
                    ],
                    'connection_type' => $device->connection_type,
                    'battery_percentage' => $device->battery_percentage,
                    'signal_strength' => $device->signal_strength,
                    'signal_strength_label' => $device->signal_strength_label,
                    'is_charging' => $device->is_charging,
                    'app_running_status' => $device->app_running_status,
                    'last_updated_at' => $device->last_updated_at,
                    'registered_at' => $device->created_at,
                    'connection_status' => $device->connection_status,
                    'battery_status' => $device->battery_status,
                    'is_online' => $device->isOnline(),
                    'current_call_status' => $device->current_call_status,
                    'current_call_number' => $device->current_call_number,
                    'call_started_at' => $device->call_started_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'pagination' => [
                        'data' => $devices->items(),
                        'current_page' => $devices->currentPage(),
                        'per_page' => $devices->perPage(),
                        'total' => $devices->total(),
                        'last_page' => $devices->lastPage(),
                        'from' => $devices->firstItem(),
                        'to' => $devices->lastItem(),
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch devices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $device = Device::with('user:id,name,email')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $device->id,
                    'user' => $device->user,
                    'device_id' => $device->device_id,
                    'device_model' => $device->device_model,
                    'manufacturer' => $device->manufacturer,
                    'os_version' => $device->os_version,
                    'app_version' => $device->app_version,
                    'permissions' => [
                        'read_call_log' => $device->perm_read_call_log,
                        'read_phone_state' => $device->perm_read_phone_state,
                        'read_contacts' => $device->perm_read_contacts,
                        'read_external_storage' => $device->perm_read_external_storage,
                        'read_media_audio' => $device->perm_read_media_audio,
                        'post_notifications' => $device->perm_post_notifications,
                    ],
                    'connection_type' => $device->connection_type,
                    'battery_percentage' => $device->battery_percentage,
                    'signal_strength' => $device->signal_strength,
                    'signal_strength_label' => $device->signal_strength_label,
                    'is_charging' => $device->is_charging,
                    'app_running_status' => $device->app_running_status,
                    'last_updated_at' => $device->last_updated_at,
                    'registered_at' => $device->created_at,
                    'connection_status' => $device->connection_status,
                    'battery_status' => $device->battery_status,
                    'is_online' => $device->isOnline(),
                    'current_call_status' => $device->current_call_status,
                    'current_call_number' => $device->current_call_number,
                    'call_started_at' => $device->call_started_at,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $device = Device::with('user')->findOrFail($id);
            $admin = $request->user('admin');

            // Store device information before deletion for logging
            $deviceData = [
                'device_id' => $device->id,
                'user_id' => $device->user_id,
                'device_name' => $device->device_name,
                'device_model' => $device->device_model,
                'device_id_value' => $device->device_id,
            ];

            // If device has a user, log them out
            if ($device->user) {
                // Clear the user's active device ID if it matches this device
                if ($device->user->active_device_id === $device->device_id) {
                    $device->user->update(['active_device_id' => null]);
                }

                // Revoke all authentication tokens for this user to force logout
                $device->user->tokens()->delete();
            }

            // Log the device removal activity before deletion
            DeviceActivity::create([
                'device_id' => $deviceData['device_id'],
                'user_id' => $deviceData['user_id'],
                'admin_id' => $admin->id,
                'action_type' => 'removal',
                'device_name' => $deviceData['device_name'],
                'device_model' => $deviceData['device_model'],
                'device_id_value' => $deviceData['device_id_value'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'notes' => 'Admin-initiated device removal from admin panel',
                'performed_at' => now(),
            ]);

            $device->delete();

            return response()->json([
                'success' => true,
                'message' => 'Device deleted successfully and user logged out'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete device',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request, $id)
    {
        try {
            $device = Device::with('user')->findOrFail($id);
            $admin = $request->user('admin');

            // Clear the user's active device ID if it matches this device
            if ($device->user && $device->user->active_device_id === $device->device_id) {
                $device->user->update(['active_device_id' => null]);
            }

            // Mark device as logged out by setting a special flag
            $device->update([
                'should_logout' => true,
                'last_updated_at' => now(),
            ]);

            // Log the device logout activity
            DeviceActivity::create([
                'device_id' => $device->id,
                'user_id' => $device->user_id,
                'admin_id' => $admin->id,
                'action_type' => 'logout',
                'device_name' => $device->device_name,
                'device_model' => $device->device_model,
                'device_id_value' => $device->device_id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'notes' => 'Admin-initiated device logout from admin panel',
                'performed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device logout signal sent successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send logout signal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
