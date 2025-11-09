<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CallRecording extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'call_log_id',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'duration',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'file_url',
    ];

    /**
     * Get the call log that owns the recording.
     */
    public function callLog()
    {
        return $this->belongsTo(CallLog::class);
    }

    /**
     * Get the full URL for the recording file.
     */
    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::deleting(function ($recording) {
            if (Storage::disk('public')->exists($recording->file_path)) {
                Storage::disk('public')->delete($recording->file_path);
            }
        });
    }
}
