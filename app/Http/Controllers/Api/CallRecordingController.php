<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\CallRecording;
use App\Models\UploadSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Initialize a chunked upload session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initChunkedUpload(Request $request)
    {
        $validated = $request->validate([
            'call_log_id' => 'required|exists:call_logs,id',
            'total_chunks' => 'required|integer|min:1',
            'total_size' => 'required|integer|min:1|max:52428800', // Max 50MB
            'filename' => 'required|string|max:255',
            'file_type' => 'nullable|string|max:50',
            'duration' => 'nullable|integer|min:0',
            'checksum' => 'nullable|string|size:32', // MD5 hash
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

        try {
            // Generate unique upload ID
            $uploadId = Str::uuid()->toString();

            // Create upload session
            $session = UploadSession::create([
                'upload_id' => $uploadId,
                'user_id' => auth()->id(),
                'call_log_id' => $request->call_log_id,
                'total_chunks' => $request->total_chunks,
                'total_size' => $request->total_size,
                'original_filename' => $request->filename,
                'file_type' => $request->file_type,
                'duration' => $request->duration,
                'checksum' => $request->checksum,
                'status' => 'pending',
                'expires_at' => now()->addHours(24), // Session expires in 24 hours
            ]);

            // Create directory for chunks
            $chunksPath = $session->getChunksPath();
            if (!file_exists($chunksPath)) {
                mkdir($chunksPath, 0755, true);
            }

            \Log::info('Chunked upload session initialized', [
                'upload_id' => $uploadId,
                'call_log_id' => $request->call_log_id,
                'total_chunks' => $request->total_chunks,
                'total_size' => $request->total_size,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Upload session initialized',
                'data' => [
                    'upload_id' => $uploadId,
                    'expires_at' => $session->expires_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to initialize chunked upload', [
                'error' => $e->getMessage(),
                'call_log_id' => $request->call_log_id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a single chunk.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadChunk(Request $request)
    {
        $validated = $request->validate([
            'upload_id' => 'required|string|exists:upload_sessions,upload_id',
            'chunk_index' => 'required|integer|min:0',
            'chunk' => 'required|file|max:5120', // Max 5MB per chunk
            'chunk_checksum' => 'nullable|string|size:32', // MD5 of this chunk
        ]);

        try {
            // Get upload session
            $session = UploadSession::where('upload_id', $request->upload_id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload session not found or access denied',
                ], 404);
            }

            // Check if session has expired
            if ($session->expires_at && $session->expires_at->isPast()) {
                $session->update(['status' => 'failed', 'error_message' => 'Session expired']);
                return response()->json([
                    'success' => false,
                    'message' => 'Upload session has expired',
                ], 410);
            }

            // Validate chunk index
            if ($request->chunk_index >= $session->total_chunks) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid chunk index',
                ], 422);
            }

            // Get chunk file
            $chunkFile = $request->file('chunk');

            // Verify chunk checksum if provided
            if ($request->chunk_checksum) {
                $actualChecksum = md5_file($chunkFile->getRealPath());
                if ($actualChecksum !== $request->chunk_checksum) {
                    \Log::error('Chunk checksum mismatch', [
                        'upload_id' => $request->upload_id,
                        'chunk_index' => $request->chunk_index,
                        'expected' => $request->chunk_checksum,
                        'actual' => $actualChecksum,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Chunk checksum verification failed',
                    ], 422);
                }
            }

            // Save chunk to storage
            $chunksPath = $session->getChunksPath();
            $chunkFilename = sprintf('chunk_%04d', $request->chunk_index);
            $chunkPath = $chunksPath . '/' . $chunkFilename;

            // Move chunk to storage
            move_uploaded_file($chunkFile->getRealPath(), $chunkPath);

            // Update session status
            $session->update([
                'received_chunks' => $session->received_chunks + 1,
                'status' => 'uploading',
            ]);

            \Log::info('Chunk uploaded successfully', [
                'upload_id' => $request->upload_id,
                'chunk_index' => $request->chunk_index,
                'received_chunks' => $session->received_chunks,
                'total_chunks' => $session->total_chunks,
            ]);

            // Check if all chunks received
            if ($session->isComplete()) {
                return $this->mergeChunks($session);
            }

            return response()->json([
                'success' => true,
                'message' => 'Chunk uploaded successfully',
                'data' => [
                    'received_chunks' => $session->received_chunks,
                    'total_chunks' => $session->total_chunks,
                    'progress' => round(($session->received_chunks / $session->total_chunks) * 100, 2),
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to upload chunk', [
                'error' => $e->getMessage(),
                'upload_id' => $request->upload_id,
                'chunk_index' => $request->chunk_index,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload chunk: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Merge all chunks into final file.
     *
     * @param  \App\Models\UploadSession  $session
     * @return \Illuminate\Http\JsonResponse
     */
    private function mergeChunks(UploadSession $session)
    {
        try {
            $chunksPath = $session->getChunksPath();
            $tempFilePath = $session->getTempFilePath();

            // Create temp directory if it doesn't exist
            $tempDir = dirname($tempFilePath);
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Merge chunks
            $outputFile = fopen($tempFilePath, 'wb');

            for ($i = 0; $i < $session->total_chunks; $i++) {
                $chunkFilename = sprintf('chunk_%04d', $i);
                $chunkPath = $chunksPath . '/' . $chunkFilename;

                if (!file_exists($chunkPath)) {
                    throw new \Exception("Missing chunk: $i");
                }

                $chunkFile = fopen($chunkPath, 'rb');
                stream_copy_to_stream($chunkFile, $outputFile);
                fclose($chunkFile);
            }

            fclose($outputFile);

            // Verify merged file size
            $mergedSize = filesize($tempFilePath);
            if ($session->total_size && $mergedSize !== $session->total_size) {
                throw new \Exception("File size mismatch. Expected: {$session->total_size}, Got: {$mergedSize}");
            }

            // Verify checksum if provided
            if ($session->checksum) {
                $actualChecksum = md5_file($tempFilePath);
                if ($actualChecksum !== $session->checksum) {
                    throw new \Exception("File checksum mismatch. Expected: {$session->checksum}, Got: {$actualChecksum}");
                }
            }

            // Move file to final storage location
            $timestamp = time();
            $filename = $timestamp . '_' . $session->original_filename;
            $finalPath = 'recordings/' . $filename;

            // Copy to public storage
            Storage::disk('public')->put($finalPath, file_get_contents($tempFilePath));

            // Create CallRecording record
            $recording = CallRecording::create([
                'call_log_id' => $session->call_log_id,
                'file_name' => $session->original_filename,
                'file_path' => $finalPath,
                'file_size' => $mergedSize,
                'file_type' => $session->file_type,
                'duration' => $session->duration,
            ]);

            // Update session status
            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Cleanup chunks and temp file
            $this->cleanupUploadSession($session);

            \Log::info('Chunks merged successfully', [
                'upload_id' => $session->upload_id,
                'recording_id' => $recording->id,
                'file_size' => $mergedSize,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recording uploaded successfully',
                'data' => [
                    'recording' => $recording,
                ],
            ], 201);
        } catch (\Exception $e) {
            // Update session with error
            $session->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            \Log::error('Failed to merge chunks', [
                'error' => $e->getMessage(),
                'upload_id' => $session->upload_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to merge chunks: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cleanup upload session files.
     *
     * @param  \App\Models\UploadSession  $session
     * @return void
     */
    private function cleanupUploadSession(UploadSession $session)
    {
        try {
            // Delete chunks directory
            $chunksPath = $session->getChunksPath();
            if (file_exists($chunksPath)) {
                $files = glob($chunksPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($chunksPath);
            }

            // Delete temp file
            $tempFile = $session->getTempFilePath();
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            \Log::info('Upload session cleaned up', [
                'upload_id' => $session->upload_id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to cleanup upload session', [
                'error' => $e->getMessage(),
                'upload_id' => $session->upload_id,
            ]);
        }
    }

    /**
     * Get upload session status.
     *
     * @param  string  $uploadId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUploadStatus($uploadId)
    {
        $session = UploadSession::where('upload_id', $uploadId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Upload session not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'upload_id' => $session->upload_id,
                'status' => $session->status,
                'received_chunks' => $session->received_chunks,
                'total_chunks' => $session->total_chunks,
                'progress' => round(($session->received_chunks / $session->total_chunks) * 100, 2),
                'error_message' => $session->error_message,
                'expires_at' => $session->expires_at ? $session->expires_at->toIso8601String() : null,
            ],
        ], 200);
    }
}
