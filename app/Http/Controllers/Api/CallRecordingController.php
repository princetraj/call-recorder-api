<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\CallRecording;
use Illuminate\Http\Request;

class CallRecordingController extends Controller
{
    /**
     * Upload a call recording.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'call_log_id' => 'required|exists:call_logs,id',
            'recording' => 'required|file|mimes:m4a,mp3,wav,3gp,amr,ogg,aac,flac,mp4,3gpp|max:51200', // max 50MB - Accept audio/video files
            'duration' => 'nullable|integer|min:0',
        ]);

        // Check if call log exists and belongs to user
        $callLog = CallLog::where('id', $request->call_log_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$callLog) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to upload recording for this call log',
            ], 403);
        }

        // Validate: Reject recordings for missed/rejected calls or zero duration calls
        if (in_array($callLog->call_type, ['missed', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot upload recording for missed or rejected calls',
            ], 422);
        }

        if ($callLog->call_duration == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot upload recording for calls with zero duration',
            ], 422);
        }

        // IMPROVED: Use try-catch for file operations and add verification
        try {
            // CRITICAL: Check for duplicate recording upload
            // Prevents duplicate uploads if retry logic causes re-submission
            $existingRecording = CallRecording::where('call_log_id', $request->call_log_id)
                ->where('file_size', $request->file('recording')->getSize())
                ->first();

            if ($existingRecording) {
                \Log::info('Duplicate recording detected, returning existing', [
                    'recording_id' => $existingRecording->id,
                    'call_log_id' => $request->call_log_id,
                    'file_size' => $request->file('recording')->getSize(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Recording already exists (duplicate prevented)',
                    'data' => [
                        'recording' => $existingRecording,
                    ],
                ], 200);
            }

            // Handle file upload
            $file = $request->file('recording');
            $timestamp = time();
            $originalName = $file->getClientOriginalName();
            $filename = $timestamp . '_' . $originalName;

            // Store in public/recordings
            $path = $file->storeAs('recordings', $filename, 'public');

            // CRITICAL: Verify file was actually written to disk
            $fullPath = storage_path('app/public/' . $path);
            if (!file_exists($fullPath)) {
                \Log::error('Recording file not found after upload', [
                    'path' => $path,
                    'full_path' => $fullPath,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'File upload failed - file not written to disk',
                ], 500);
            }

            // Verify file size matches
            $uploadedSize = filesize($fullPath);
            if ($uploadedSize !== $file->getSize()) {
                \Log::error('Recording file size mismatch', [
                    'expected' => $file->getSize(),
                    'actual' => $uploadedSize,
                    'path' => $path,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'File upload incomplete - size mismatch',
                ], 500);
            }

            // Create database record
            $recording = CallRecording::create([
                'call_log_id' => $request->call_log_id,
                'file_name' => $originalName,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'duration' => $request->duration,
            ]);

            \Log::info('Recording uploaded successfully', [
                'recording_id' => $recording->id,
                'call_log_id' => $request->call_log_id,
                'file_size' => $file->getSize(),
                'file_name' => $originalName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recording uploaded successfully',
                'data' => [
                    'recording' => $recording,
                ],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to upload recording', [
                'error' => $e->getMessage(),
                'call_log_id' => $request->call_log_id,
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload recording: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all recordings for a specific call log.
     *
     * @param  int  $callLogId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($callLogId)
    {
        // Check if call log exists and belongs to user
        $callLog = CallLog::where('id', $callLogId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$callLog) {
            return response()->json([
                'success' => false,
                'message' => 'Call log not found',
            ], 404);
        }

        $recordings = $callLog->recordings;

        return response()->json([
            'success' => true,
            'message' => 'Recordings retrieved successfully',
            'data' => [
                'recordings' => $recordings,
            ],
        ], 200);
    }

    /**
     * Delete a recording.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $recording = CallRecording::with('callLog')->find($id);

        if (!$recording) {
            return response()->json([
                'success' => false,
                'message' => 'Recording not found',
            ], 404);
        }

        // Check if user owns this recording
        if ($recording->callLog->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this recording',
            ], 403);
        }

        // Delete the recording (file will be deleted by model event)
        $recording->delete();

        return response()->json([
            'success' => true,
            'message' => 'Recording deleted successfully',
        ], 200);
    }
}
