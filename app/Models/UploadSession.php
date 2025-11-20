<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'upload_id',
        'user_id',
        'call_log_id',
        'total_chunks',
        'received_chunks',
        'total_size',
        'original_filename',
        'file_type',
        'duration',
        'checksum',
        'status',
        'error_message',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the upload session.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the call log associated with this upload.
     */
    public function callLog()
    {
        return $this->belongsTo(CallLog::class);
    }

    /**
     * Get the path where chunks are stored.
     */
    public function getChunksPath()
    {
        return storage_path('app/chunks/' . $this->upload_id);
    }

    /**
     * Get the temporary file path for the merged file.
     */
    public function getTempFilePath()
    {
        return storage_path('app/temp/' . $this->upload_id . '_' . $this->original_filename);
    }

    /**
     * Check if all chunks have been received.
     */
    public function isComplete()
    {
        return $this->received_chunks >= $this->total_chunks;
    }
}
