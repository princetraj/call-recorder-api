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
        $request->validate([
            'call_log_id' => 'required|exists:call_logs,id',
            'recording' => 'required|file|mimes:mp3,wav,m4a,3gp|max:51200', // max 50MB
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

        // Handle file upload
        $file = $request->file('recording');
        $timestamp = time();
        $originalName = $file->getClientOriginalName();
        $filename = $timestamp . '_' . $originalName;

        // Store in public/recordings
        $path = $file->storeAs('recordings', $filename, 'public');

        // Create database record
        $recording = CallRecording::create([
            'call_log_id' => $request->call_log_id,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'duration' => $request->duration,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recording uploaded successfully',
            'data' => [
                'recording' => $recording,
            ],
        ], 201);
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
