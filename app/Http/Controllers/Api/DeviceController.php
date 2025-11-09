<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
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
                    'user_id' => $request->user()->id
                ],
                [
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

            $device->update([
                'connection_type' => $request->connection_type ?? $device->connection_type,
                'battery_percentage' => $request->battery_percentage ?? $device->battery_percentage,
                'signal_strength' => $request->signal_strength ?? $device->signal_strength,
                'is_charging' => $request->is_charging ?? $device->is_charging,
                'app_running_status' => $request->app_running_status ?? $device->app_running_status,
                'current_call_status' => $request->current_call_status ?? $device->current_call_status,
                'current_call_number' => $request->current_call_number ?? $device->current_call_number,
                'call_started_at' => $request->current_call_status && $request->current_call_status !== 'idle' ? ($device->current_call_status === 'idle' ? now() : $device->call_started_at) : null,
                'last_updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device status updated successfully',
                'data' => $device->fresh()
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
            $query = Device::with('user:id,name,email')
                ->orderBy('last_updated_at', 'desc');

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

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

            $perPage = $request->input('per_page', 20);
            $devices = $query->paginate($perPage);

            $devices->getCollection()->transform(function ($device) {
                return [
                    'id' => $device->id,
                    'user' => $device->user,
                    'device_id' => $device->device_id,
                    'device_model' => $device->device_model,
                    'manufacturer' => $device->manufacturer,
                    'os_version' => $device->os_version,
                    'app_version' => $device->app_version,
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

    public function destroy($id)
    {
        try {
            $device = Device::findOrFail($id);
            $device->delete();

            return response()->json([
                'success' => true,
                'message' => 'Device deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete device',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
